<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\AddressDataFactory;

/**
 * A fluent contact for one owner, started from `Contacts::for($owner)->email(…)`,
 * `->phone(…)`, `->type(…)`, …; `add()` stores it through the owner's contact book.
 */
final class PendingContact
{
    private ContactType $type = ContactType::Custom;

    /** The raw kind when it is a registered custom kind (e.g. `whatsapp`); null = the type's own. */
    private ?string $kind = null;

    private string $value = '';

    private ?string $label = null;

    private ?string $name = null;

    private ?string $category = null;

    private bool $isPrimary = false;

    private ?AddressData $structuredAddress = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    /**
     * @internal start one with `Contacts::for($owner)->email(…)` and friends
     */
    public function __construct(
        private readonly ContactBook $book,
    ) {}

    public function type(ContactType|string $type): self
    {
        $this->type = $type instanceof ContactType
            ? $type
            : ContactType::fromValueOrCustom($type);

        // Keep the raw string: a registered custom kind types as Custom, but its label,
        // icon and validation rules are keyed by the kind itself.
        $this->kind = is_string($type) && $type !== '' ? $type : null;

        return $this;
    }

    public function email(string $value): self
    {
        $this->type = ContactType::Email;
        $this->kind = null;
        $this->value = $value;

        return $this;
    }

    public function phone(string $value): self
    {
        $this->type = ContactType::Phone;
        $this->kind = null;
        $this->value = $value;

        return $this;
    }

    public function url(string $value): self
    {
        $this->type = ContactType::Url;
        $this->kind = null;
        $this->value = $value;

        return $this;
    }

    public function address(string $value): self
    {
        $this->type = ContactType::Address;
        $this->kind = null;
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
     * one-line render. Accepts an AddressData or an attribute array. A contact of
     * any kind other than address is refused on add().
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
            $this->kind = null;
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
        return $this->book->add(new ContactData(
            type: $this->type,
            value: $this->value,
            label: $this->label,
            name: $this->name,
            category: $this->category,
            isPrimary: $this->isPrimary,
            meta: $this->meta,
            kind: $this->kind,
            address: $this->structuredAddress,
        ));
    }
}
