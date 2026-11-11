<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\ContactNotOwned;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\VCardExporter;

/**
 * `Contacts::for($owner)` — one owner's contacts. Every write goes through the manager,
 * so host overrides and `Contacts::fake()` see it.
 */
final readonly class ContactBook
{
    /**
     * @internal build it with `Contacts::for($owner)`
     */
    public function __construct(
        private ContactsManager $contacts,
        private Model $owner,
    ) {}

    /**
     * Start a fluent email contact; `add()` stores it.
     */
    public function email(string $value): PendingContact
    {
        return $this->pending()->email($value);
    }

    /**
     * Start a fluent phone contact; `add()` stores it.
     */
    public function phone(string $value): PendingContact
    {
        return $this->pending()->phone($value);
    }

    /**
     * Start a fluent URL contact; `add()` stores it.
     */
    public function url(string $value): PendingContact
    {
        return $this->pending()->url($value);
    }

    /**
     * Start a fluent free-text address contact; `add()` stores it.
     */
    public function address(string $value): PendingContact
    {
        return $this->pending()->address($value);
    }

    /**
     * Start a fluent address contact backed by a structured postal address.
     *
     * @param  AddressData|array<string, mixed>  $data
     */
    public function structuredAddress(AddressData|array $data): PendingContact
    {
        return $this->pending()->structuredAddress($data);
    }

    /**
     * Start a fluent contact of any kind — a ContactType or a registered custom kind.
     */
    public function type(ContactType|string $type): PendingContact
    {
        return $this->pending()->type($type);
    }

    public function add(ContactData $data): Contact
    {
        return $this->contacts->addFor($this->owner, $data);
    }

    /**
     * Reconcile this owner's contacts of one kind — a ContactType or a registered custom kind
     * such as `whatsapp` — to the given set: matching values are updated in place, new ones
     * created, the rest deleted; positions follow input order, and every item takes the
     * synced kind.
     *
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function sync(ContactType|string $type, array $items): EloquentCollection
    {
        return $this->contacts->syncFor($this->owner, $type, $items);
    }

    /**
     * `Contacts::update()`, refused unless the contact belongs to this owner.
     *
     * @throws ContactNotOwned
     */
    public function update(Contact $contact, ContactData $data): Contact
    {
        return $this->contacts->update($this->owned($contact), $data);
    }

    /**
     * `Contacts::delete()`, refused unless the contact belongs to this owner.
     *
     * @throws ContactNotOwned
     */
    public function delete(Contact $contact): void
    {
        $this->contacts->delete($this->owned($contact));
    }

    /**
     * `Contacts::setPrimary()`, refused unless the contact belongs to this owner.
     *
     * @throws ContactNotOwned
     */
    public function setPrimary(Contact $contact): Contact
    {
        return $this->contacts->setPrimary($this->owned($contact));
    }

    /**
     * Every contact of this owner, by position.
     *
     * @return EloquentCollection<int, Contact>
     */
    public function all(): EloquentCollection
    {
        return $this->query()->get();
    }

    /**
     * @return EloquentCollection<int, Contact>
     */
    public function ofType(ContactType|string $type): EloquentCollection
    {
        return $this->query()->ofType($type)->get();
    }

    /**
     * The primary contact of one kind, if any.
     */
    public function primary(ContactType|string $type): ?Contact
    {
        return $this->query()->ofType($type)->primary()->first();
    }

    /**
     * A vCard 3.0 string of this owner's contacts.
     */
    public function vCard(): string
    {
        return VCardExporter::forOwner($this->owner);
    }

    /**
     * @return Builder<Contact>
     */
    private function query(): Builder
    {
        return ContactModel::class()::query()->forOwner($this->owner)->ordered();
    }

    /**
     * The contact, if this owner holds it: the same morph type and the same key, compared
     * as strings so int, uuid and ulid keys all match the stored `owner_id`. An owner-less
     * contact and an owner without a key never match.
     *
     * @throws ContactNotOwned
     */
    private function owned(Contact $contact): Contact
    {
        $key = $this->owner->getKey();
        $ownerId = $contact->getAttribute('owner_id');

        $owns = is_scalar($key) && $key !== ''
            && is_scalar($ownerId)
            && $contact->getAttribute('owner_type') === $this->owner->getMorphClass()
            && (string) $ownerId === (string) $key;

        if (! $owns) {
            throw ContactNotOwned::make();
        }

        return $contact;
    }

    private function pending(): PendingContact
    {
        return new PendingContact($this);
    }
}
