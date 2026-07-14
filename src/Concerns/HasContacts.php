<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
use RoundlyConsulting\Contacts\Support\ContactModel;

/**
 * @phpstan-require-extends Model
 */
trait HasContacts
{
    /**
     * @return MorphMany<Contact, $this>
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(ContactModel::class(), 'owner');
    }

    public function addContact(ContactData $data): Contact
    {
        return app(ContactsManager::class)->add($this, $data);
    }

    public function addEmail(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Email,
            value: $value,
            label: $label,
            isPrimary: $primary,
        ));
    }

    public function addPhone(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Phone,
            value: $value,
            label: $label,
            isPrimary: $primary,
        ));
    }

    public function addUrl(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Url,
            value: $value,
            label: $label,
            isPrimary: $primary,
        ));
    }

    public function addAddress(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Address,
            value: $value,
            label: $label,
            isPrimary: $primary,
        ));
    }

    /**
     * Create an address-type contact backed by a validated, structured Address.
     * The contact's value mirrors the address's one-line render, so existing
     * readers keep working while the structured data lives on the Address.
     */
    public function addStructuredAddress(AddressData $data, ?string $label = null, bool $primary = false): Contact
    {
        $contact = $this->addContact(new ContactData(
            type: ContactType::Address,
            value: ContactAddressFormatter::fromData($data),
            label: $label,
            isPrimary: $primary,
        ));

        ContactAddressFormatter::attach($contact, $data);

        return $contact;
    }

    /**
     * @return EloquentCollection<int, Contact>
     */
    public function contactsOfType(ContactType|string $type): EloquentCollection
    {
        /** @var EloquentCollection<int, Contact> $contacts */
        $contacts = $this->contacts()->ofType($type)->ordered()->get();

        return $contacts;
    }

    public function primaryContact(ContactType|string $type): ?Contact
    {
        return $this->contacts()->ofType($type)->primary()->ordered()->first();
    }

    public function primaryEmail(): ?Contact
    {
        return $this->primaryContact(ContactType::Email);
    }

    public function primaryPhone(): ?Contact
    {
        return $this->primaryContact(ContactType::Phone);
    }
}
