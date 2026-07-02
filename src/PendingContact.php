<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\AddressDataFactory;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;

final class PendingContact
{
    private ContactType $type = ContactType::Custom;

    private string $value = '';

    private ?string $label = null;

    private ?string $name = null;

    private ?string $category = null;

    private bool $isPrimary = false;

    private ?AddressData $structuredAddress = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    public function __construct(
        private readonly ContactsManager $manager,
        private readonly Model $owner,
    ) {}

    public function type(ContactType|string $type): self
    {
        $this->type = $type instanceof ContactType
            ? $type
            : ContactType::fromValueOrCustom($type);

        return $this;
    }

    public function email(string $value): self
    {
        $this->type = ContactType::Email;
        $this->value = $value;

        return $this;
    }

    public function phone(string $value): self
    {
        $this->type = ContactType::Phone;
        $this->value = $value;

        return $this;
    }

    public function url(string $value): self
    {
        $this->type = ContactType::Url;
        $this->value = $value;

        return $this;
    }

    public function address(string $value): self
    {
        $this->type = ContactType::Address;
        $this->value = $value;

        return $this;
    }

    public function value(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Attach a validated, structured postal address to this address-type contact.
     * On add() the Address is created and the contact's value becomes its
     * one-line render. Accepts an AddressData or an attribute array.
     *
     * @param  AddressData|array<string, mixed>  $data
     */
    public function structuredAddress(AddressData|array $data): self
    {
        $this->structuredAddress = $data instanceof AddressData
            ? $data
            : AddressDataFactory::fromArray($data);

        if ($this->type === ContactType::Custom) {
            $this->type = ContactType::Address;
        }

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function category(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function primary(bool $primary = true): self
    {
        $this->isPrimary = $primary;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function add(): Contact
    {
        // Ensure the base row passes value validation before the structured
        // address (if any) overwrites value with its one-line render.
        $value = $this->value;

        if ($value === '' && $this->structuredAddress instanceof AddressData) {
            $value = ContactAddressFormatter::fromData($this->structuredAddress);
        }

        $contact = $this->manager->add($this->owner, new ContactData(
            type: $this->type,
            value: $value,
            label: $this->label,
            name: $this->name,
            category: $this->category,
            isPrimary: $this->isPrimary,
            meta: $this->meta,
        ));

        if ($this->structuredAddress instanceof AddressData) {
            ContactAddressFormatter::attach($contact, $this->structuredAddress);
        }

        return $contact;
    }
}
