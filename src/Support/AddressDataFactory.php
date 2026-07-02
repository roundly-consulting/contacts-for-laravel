<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;

/**
 * Builds an addresses AddressData DTO from a loose attribute array, so the
 * fluent contacts builder can accept either the DTO or a plain array.
 */
final class AddressDataFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): AddressData
    {
        $type = $attributes['type'] ?? AddressType::Default;

        if (is_string($type)) {
            $type = AddressType::tryFrom($type) ?? AddressType::Default;
        }

        $meta = $attributes['meta'] ?? null;

        return AddressData::make(
            city: self::string($attributes, 'city'),
            street: self::string($attributes, 'street'),
            postalCode: self::string($attributes, 'postalCode', 'postal_code'),
            countryIso: self::string($attributes, 'countryIso', 'country_iso'),
            name: self::nullableString($attributes, 'name'),
            type: $type instanceof AddressType ? $type : AddressType::Default,
            isPrimary: (bool) ($attributes['isPrimary'] ?? $attributes['is_primary'] ?? false),
            meta: $meta instanceof Collection ? $meta : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function string(array $attributes, string $key, ?string $alias = null): string
    {
        $value = $attributes[$key] ?? ($alias !== null ? ($attributes[$alias] ?? null) : null);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function nullableString(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
