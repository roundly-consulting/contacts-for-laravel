<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\ContactBook;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
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

    /**
     * This model's contact book — every shortcut below goes through it, so the
     * `Contacts` facade, host overrides and `Contacts::fake()` see each call.
     */
    public function contactBook(): ContactBook
    {
        return app(ContactsManager::class)->for($this);
    }

    public function addContact(ContactData $data): Contact
    {
        return $this->contactBook()->add($data);
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

    /**
     * Add a free-text address contact. Named apart from addresses' `HasAddresses::addAddress()`,
     * so a model can use both traits.
     */
    public function addAddressContact(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Address,
            value: $value,
            label: $label,
            isPrimary: $primary,
        ));
    }

    /**
     * @deprecated use addAddressContact(); removed in 2.0. It collides with addresses'
     *             `HasAddresses::addAddress(AddressData)`, so a model using both traits must
     *             resolve it: `use HasAddresses, HasContacts { HasAddresses::addAddress insteadof HasContacts; }`.
     */
    public function addAddress(string $value, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addAddressContact($value, $label, $primary);
    }

    /**
     * Create an address-type contact backed by a validated, structured Address.
     * The contact's value mirrors the address's one-line render, so existing
     * readers keep working while the structured data lives on the Address.
     */
    public function addStructuredAddress(AddressData $data, ?string $label = null, bool $primary = false): Contact
    {
        return $this->addContact(new ContactData(
            type: ContactType::Address,
            value: '',
            label: $label,
            isPrimary: $primary,
            address: $data,
        ));
    }

    /**
     * @return EloquentCollection<int, Contact>
     */
    public function contactsOfType(ContactType|string $type): EloquentCollection
    {
        return $this->contactBook()->ofType($type);
    }

    public function primaryContact(ContactType|string $type): ?Contact
    {
        return $this->contactBook()->primary($type);
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
