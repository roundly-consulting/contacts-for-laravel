<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactKind;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\ContactPreflight;

final readonly class SyncContactsAction
{
    public function __construct(
        private AddContactAction $add,
        private UpdateContactAction $update,
        private DeleteContactAction $delete,
    ) {}

    /**
     * Reconcile an owner's contacts of a single kind to match the given set.
     *
     * The kind is a ContactType or a raw kind string, so a registered custom kind (e.g.
     * `whatsapp`) is synced as itself. Every item takes that kind. Existing contacts whose
     * value matches are updated in place; missing ones are created; absent ones are deleted.
     * Positions follow input order. When the primary is synced away, the first synced
     * contact takes over (auto_primary).
     *
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function execute(Model $owner, ContactType|string $type, array $items): EloquentCollection
    {
        $kind = ContactKind::of($type);
        $type = ContactType::fromValueOrCustom($kind);

        /** @var EloquentCollection<int, Contact> $existing */
        $existing = $owner->morphMany(ContactModel::class(), 'owner')
            ->where('type', $kind)
            ->get();

        /** @var EloquentCollection<int, Contact> $result */
        $result = new EloquentCollection;
        $keptIds = [];

        $position = 0;

        foreach ($items as $item) {
            $data = new ContactData(
                type: $type,
                value: $item->value,
                label: $item->label,
                name: $item->name,
                category: $item->category,
                isPrimary: $item->isPrimary,
                position: $position,
                meta: $item->meta,
                kind: $kind,
                address: $item->address,
            );

            // Matched on the value as it would be stored — an address-only item's is the render
            // of its structured address, never the blank it was given.
            $data = ContactPreflight::prepare($data);

            $match = $existing->first(
                fn (Contact $contact): bool => $contact->value === $data->value,
            );

            if ($match instanceof Contact) {
                $contact = $this->update->execute($match, $data);
                $keptIds[] = $match->getKey();
            } else {
                $contact = $this->add->execute($owner, $data);
            }

            $result->push($contact);
            $position++;
        }

        // The primary goes last: deleting it promotes the next contact by position, and by
        // then only the synced set is left to promote from.
        $stale = $existing
            ->reject(static fn (Contact $contact): bool => in_array($contact->getKey(), $keptIds, true))
            ->sortBy(static fn (Contact $contact): int => $contact->is_primary ? 1 : 0);

        // A primary flag on an item demotes whichever contact held the primary before —
        // possibly one synced earlier in this loop, whose copy above still says primary.
        $primaryMoved = array_any($items, static fn (ContactData $item): bool => $item->isPrimary);

        foreach ($stale as $contact) {
            $this->delete->execute($contact);
            $primaryMoved = $primaryMoved || $contact->is_primary;
        }

        // Deleting the primary promoted a synced contact in the database, or a flagged item
        // demoted an earlier one; hand back what is stored, not the copies taken before.
        return $primaryMoved ? $result->fresh() : $result;
    }
}
