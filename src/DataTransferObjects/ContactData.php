<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\DataTransferObjects;

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\AddressDataFactory;
use RoundlyConsulting\Contacts\Support\ContactKind;

final readonly class ContactData
{
    /**
     * The raw kind this contact is stored under: one of the six built-in ContactType
     * values, or a custom kind registered in `config('contacts.types')` (e.g. `whatsapp`).
     *
     * Kept alongside {@see self::$type} rather than folded into it. A kind outside the six
     * built-ins still types as `ContactType::Custom` — that contract is unchanged — but the
     * registry is keyed by the raw string, so discarding it (as this DTO used to) made the
     * whole `contacts.types` feature inert: the label fell back to "Other", the icon to
     * "identification", and a host's registered validation rules never ran.
     *
     * Defaults to the type's own value, so a built-in kind needs no extra argument.
     */
    public string $kind;

    /**
     * @param  array<string, mixed>  $meta
     * @param  AddressData|null  $address  a structured postal address to attach — address-type
     *                                     contacts only. The contact's value then mirrors the
     *                                     address's one-line render (and defaults to it when empty).
     */
    public function __construct(
        public ContactType $type,
        public string $value,
        public ?string $label = null,
        public ?string $name = null,
        public ?string $category = null,
        public bool $isPrimary = false,
        public ?int $position = null,
        public array $meta = [],
        ?string $kind = null,
        public ?AddressData $address = null,
    ) {
        $this->kind = $kind ?? $type->value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $type = $attributes['type'] ?? ContactType::Custom;
        $kind = null;

        if ($type instanceof ContactType) {
            $kind = $type->value;
        } elseif (is_string($type) && $type !== '') {
            // The raw string is kept BEFORE the enum collapses it to Custom.
            $kind = $type;
            $type = ContactType::fromValueOrCustom($type);
        } else {
            $type = ContactType::Custom;
        }

        // An explicit kind wins, so a caller can pin the kind independently of the type.
        if (isset($attributes['kind']) && is_string($attributes['kind']) && $attributes['kind'] !== '') {
            $kind = $attributes['kind'];
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [];

        $address = $attributes['address'] ?? null;

        if (is_array($address)) {
            $address = AddressDataFactory::fromArray($address);
        }

        return new self(
            type: $type,
            value: (string) ($attributes['value'] ?? ''),
            label: self::nullableString($attributes['label'] ?? null),
            name: self::nullableString($attributes['name'] ?? null),
            category: self::nullableString($attributes['category'] ?? null),
            isPrimary: (bool) ($attributes['isPrimary'] ?? $attributes['is_primary'] ?? false),
            position: isset($attributes['position']) ? (int) $attributes['position'] : null,
            meta: $meta,
            kind: $kind,
            address: $address instanceof AddressData ? $address : null,
        );
    }

    /**
     * Human label for this contact's kind — the registered one for a custom kind, the
     * type's own otherwise. Distinct from {@see self::$label}, which is the host's free-text
     * label for this particular contact (e.g. "Work").
     */
    public function kindLabel(): string
    {
        return ContactKind::label($this->type, $this->kind);
    }

    /**
     * Icon name for this contact's kind.
     */
    public function kindIcon(): string
    {
        return ContactKind::icon($this->type, $this->kind);
    }

    /**
     * Validation rules for this contact's kind — the host's registered rules for a custom
     * kind, the type's own otherwise.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        return ContactKind::validationRules($this->type, $this->kind);
    }

    /**
     * Return a copy with the value normalized for its kind.
     */
    public function normalized(): self
    {
        return $this->withValue($this->type->normalize($this->value));
    }

    /**
     * Return a copy carrying another value.
     */
    public function withValue(string $value): self
    {
        return new self(
            type: $this->type,
            value: $value,
            label: $this->label,
            name: $this->name,
            category: $this->category,
            isPrimary: $this->isPrimary,
            position: $this->position,
            meta: $this->meta,
            kind: $this->kind,
            address: $this->address,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
