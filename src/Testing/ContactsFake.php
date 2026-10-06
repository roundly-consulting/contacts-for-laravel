<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Testing;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactKind;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\ContactPreflight;
use RoundlyConsulting\PackageToolkit\Support\Config;
use SensitiveParameter;

/**
 * Test double for the contacts manager, installed by `Contacts::fake()`. Nothing is
 * written and no event fires: added contacts come back unsaved (a structured address is
 * rendered into the value, never stored), and every add, sync, update, delete, primary
 * change and verification step — through the facade, an injected manager, a contact
 * book, the verification accessor, the HasContacts trait or a Contact model method — is
 * recorded for the assertions below. Reads still hit the database.
 *
 * Adds and syncs are checked like the real ones — the same `InvalidContactValue` for an
 * invalid value or a structured address on a non-address kind — and come back as the real
 * ones would be stored: normalized, kinded, positioned, and primary when first of a kind.
 */
final class ContactsFake extends ContactsManager
{
    /** @var list<array{owner: Model, data: ContactData, contact: Contact}> */
    private array $added = [];

    /** @var list<array{owner: Model, kind: string, items: list<ContactData>}> */
    private array $synced = [];

    /** @var list<array{contact: Contact, data: ContactData}> */
    private array $updated = [];

    /** @var list<Contact> */
    private array $deleted = [];

    /** @var list<Contact> */
    private array $primarySet = [];

    /** @var list<Contact> */
    private array $verified = [];

    /** @var list<Contact> */
    private array $verificationRequested = [];

    /** @var list<Contact> */
    private array $verificationConfirmed = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Checked, normalized, kinded and positioned exactly as the real add would store it —
     * first of its kind is primary while `contacts.auto_primary` is on — judged against the
     * stored contacts plus the ones this fake already handed out, since it writes nothing.
     *
     * @throws InvalidContactValue
     */
    public function addFor(Model $owner, ContactData $data): Contact
    {
        $prepared = ContactPreflight::prepare($data);
        $pending = $this->addedFor($owner, $prepared->kind);

        $isFirstOfKind = $pending === [] && ! $this->stored($owner, $prepared->kind)->exists();

        $contact = $this->unsaved(
            $owner,
            $prepared,
            isPrimary: $prepared->isPrimary || ($isFirstOfKind && Config::boolean('contacts.auto_primary', true)),
            position: $prepared->position ?? $this->nextPosition($owner, $prepared->kind, $pending),
        );

        $this->added[] = ['owner' => $owner, 'data' => $data, 'contact' => $contact];

        return $contact;
    }

    /**
     * Rebuilt item by item as the real sync rebuilds them — the synced kind, positions in
     * input order, the same checks — with the primary flag the stored set would end on.
     *
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     *
     * @throws InvalidContactValue
     */
    public function syncFor(Model $owner, ContactType|string $type, array $items): EloquentCollection
    {
        $kind = ContactKind::of($type);

        $prepared = [];

        foreach ($items as $item) {
            $prepared[] = ContactPreflight::prepareSyncItem($item, $kind, count($prepared));
        }

        $this->synced[] = ['owner' => $owner, 'kind' => $kind, 'items' => $items];

        $primary = $this->syncedPrimary($owner, $kind, $prepared);

        $contacts = [];

        foreach ($prepared as $position => $data) {
            $contacts[] = $this->unsaved($owner, $data, isPrimary: $position === $primary, position: $position);
        }

        return new EloquentCollection($contacts);
    }

    public function update(Contact $contact, ContactData $data): Contact
    {
        $this->updated[] = ['contact' => $contact, 'data' => $data];

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $this->deleted[] = $contact;
    }

    public function setPrimary(Contact $contact): Contact
    {
        $this->primarySet[] = $contact;

        return $contact;
    }

    public function requestVerification(Contact $contact): string
    {
        $this->verificationRequested[] = $contact;

        return 'fake-token';
    }

    public function confirmVerification(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        $this->verificationConfirmed[] = $contact;

        return $contact;
    }

    public function markVerified(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        $this->verified[] = $contact;

        return $contact;
    }

    /**
     * @param  (Closure(ContactData, Model): bool)|null  $callback
     */
    public function assertAdded(?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->added,
            fn (array $added): bool => $callback === null || $callback($added['data'], $added['owner']) === true,
        );

        PHPUnit::assertNotSame([], $matches, $callback === null ? 'No contact was added.' : 'No matching contact was added.');
    }

    public function assertNothingAdded(): void
    {
        PHPUnit::assertSame([], $this->added, 'A contact was added unexpectedly.');
    }

    /**
     * @param  ContactType|string|null  $type  the synced kind — a ContactType or a raw kind such as `whatsapp`
     * @param  (Closure(list<ContactData>, Model): bool)|null  $callback
     */
    public function assertSynced(ContactType|string|null $type = null, ?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->synced,
            fn (array $synced): bool => ($type === null || $synced['kind'] === ContactKind::of($type))
                && ($callback === null || $callback($synced['items'], $synced['owner']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching contacts were synced.');
    }

    public function assertNothingSynced(): void
    {
        PHPUnit::assertSame([], $this->synced, 'Contacts were synced unexpectedly.');
    }

    /**
     * @param  (Closure(ContactData): bool)|null  $callback
     */
    public function assertUpdated(?Contact $contact = null, ?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->updated,
            fn (array $updated): bool => ($contact === null || $this->same($updated['contact'], $contact))
                && ($callback === null || $callback($updated['data']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching contact was updated.');
    }

    public function assertNothingUpdated(): void
    {
        PHPUnit::assertSame([], $this->updated, 'A contact was updated unexpectedly.');
    }

    public function assertDeleted(?Contact $contact = null): void
    {
        $this->assertRecorded($this->deleted, $contact, 'No matching contact was deleted.');
    }

    public function assertNothingDeleted(): void
    {
        PHPUnit::assertSame([], $this->deleted, 'A contact was deleted unexpectedly.');
    }

    public function assertPrimarySet(?Contact $contact = null): void
    {
        $this->assertRecorded($this->primarySet, $contact, 'No matching contact was set as primary.');
    }

    public function assertNothingPrimarySet(): void
    {
        PHPUnit::assertSame([], $this->primarySet, 'A primary contact was set unexpectedly.');
    }

    public function assertVerified(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verified, $contact, 'No matching contact was marked verified.');
    }

    public function assertNothingVerified(): void
    {
        PHPUnit::assertSame([], $this->verified, 'A contact was marked verified unexpectedly.');
    }

    public function assertVerificationRequested(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verificationRequested, $contact, 'No matching contact verification was requested.');
    }

    public function assertNoVerificationRequested(): void
    {
        PHPUnit::assertSame([], $this->verificationRequested, 'A contact verification was requested unexpectedly.');
    }

    public function assertVerificationConfirmed(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verificationConfirmed, $contact, 'No matching contact verification was confirmed.');
    }

    public function assertNoVerificationConfirmed(): void
    {
        PHPUnit::assertSame([], $this->verificationConfirmed, 'A contact verification was confirmed unexpectedly.');
    }

    /**
     * @param  list<Contact>  $recorded
     */
    private function assertRecorded(array $recorded, ?Contact $contact, string $message): void
    {
        $matches = array_filter(
            $recorded,
            fn (Contact $candidate): bool => $contact === null || $this->same($candidate, $contact),
        );

        PHPUnit::assertNotSame([], $matches, $message);
    }

    /**
     * An unsaved contact carrying the prepared data, owned by the owner — what the real add
     * would have stored, minus the row. A structured address is rendered, never stored.
     */
    private function unsaved(Model $owner, ContactData $data, bool $isPrimary, int $position): Contact
    {
        $model = ContactModel::class();

        return (new $model)->forceFill([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'type' => $data->kind,
            'name' => $data->name ?? '',
            'value' => $data->value,
            'label' => $data->label,
            'category' => $data->category,
            'is_primary' => $isPrimary,
            'position' => $position,
            'meta' => $data->meta,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * The owner's stored, live contacts of one kind.
     *
     * @return Builder<Contact>
     */
    private function stored(Model $owner, string $kind): Builder
    {
        return ContactModel::class()::query()->forOwner($owner)->ofType($kind);
    }

    /**
     * The contacts this fake already handed out to the owner for one kind.
     *
     * @return list<Contact>
     */
    private function addedFor(Model $owner, string $kind): array
    {
        return array_values(array_map(
            static fn (array $added): Contact => $added['contact'],
            array_filter(
                $this->added,
                static fn (array $added): bool => $added['owner']->is($owner) && $added['contact']->kind === $kind,
            ),
        ));
    }

    /**
     * After the last stored or handed-out contact of the kind, as the real add numbers them.
     *
     * @param  list<Contact>  $pending
     */
    private function nextPosition(Model $owner, string $kind, array $pending): int
    {
        $positions = array_map(static fn (Contact $contact): int => $contact->position, $pending);
        $stored = $this->stored($owner, $kind)->max('position');

        if (is_numeric($stored)) {
            $positions[] = (int) $stored;
        }

        return $positions === [] ? 0 : max($positions) + 1;
    }

    /**
     * The index of the synced item the real sync would leave primary: the last one flagged,
     * else the one matching the stored primary, else — while `contacts.auto_primary` is on
     * and the kind was empty (first of its kind) or its primary is synced away — the first.
     *
     * @param  list<ContactData>  $items
     */
    private function syncedPrimary(Model $owner, string $kind, array $items): ?int
    {
        $flagged = array_keys(array_filter($items, static fn (ContactData $item): bool => $item->isPrimary));

        if ($flagged !== []) {
            return max($flagged);
        }

        $stored = $this->stored($owner, $kind)->get();
        $primary = $stored->first(static fn (Contact $contact): bool => $contact->is_primary);

        if ($primary instanceof Contact) {
            foreach ($items as $position => $item) {
                if ($item->value === $primary->value) {
                    return $position;
                }
            }
        }

        $promotes = $stored->isEmpty() || $primary instanceof Contact;

        return $items !== [] && $promotes && Config::boolean('contacts.auto_primary', true) ? 0 : null;
    }

    /**
     * Unsaved contacts carry no key, so they match by identity; stored ones by key.
     */
    private function same(Contact $a, Contact $b): bool
    {
        return $a === $b || ($a->exists && $b->exists && $a->is($b));
    }
}
