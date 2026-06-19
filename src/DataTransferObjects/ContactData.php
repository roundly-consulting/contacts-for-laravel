<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\DataTransferObjects;

use RoundlyConsulting\Contacts\Enums\ContactType;

final readonly class ContactData
{
    /**
     * @param  array<string, mixed>  $meta
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
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $type = $attributes['type'] ?? ContactType::Custom;

        if (is_string($type)) {
            $type = ContactType::fromValueOrCustom($type);
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [];

        return new self(
            type: $type instanceof ContactType ? $type : ContactType::Custom,
            value: (string) ($attributes['value'] ?? ''),
            label: self::nullableString($attributes['label'] ?? null),
            name: self::nullableString($attributes['name'] ?? null),
            category: self::nullableString($attributes['category'] ?? null),
            isPrimary: (bool) ($attributes['isPrimary'] ?? $attributes['is_primary'] ?? false),
            position: isset($attributes['position']) ? (int) $attributes['position'] : null,
            meta: $meta,
        );
    }

    /**
     * Return a copy with the value normalized for its kind.
     */
    public function normalized(): self
    {
        return new self(
            type: $this->type,
            value: $this->type->normalize($this->value),
            label: $this->label,
            name: $this->name,
            category: $this->category,
            isPrimary: $this->isPrimary,
            position: $this->position,
            meta: $this->meta,
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
