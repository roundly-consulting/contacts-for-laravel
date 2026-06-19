<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

final class PendingContact
{
    private ContactType $type = ContactType::Custom;

    private string $value = '';

    private ?string $label = null;

    private ?string $name = null;

    private ?string $category = null;

    private bool $isPrimary = false;

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
        return $this->manager->add($this->owner, new ContactData(
            type: $this->type,
            value: $this->value,
            label: $this->label,
            name: $this->name,
            category: $this->category,
            isPrimary: $this->isPrimary,
            meta: $this->meta,
        ));
    }
}
