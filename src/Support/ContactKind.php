<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * Resolves the label, icon and validation rules for a contact's **raw kind**.
 *
 * A kind is the string a contact is actually stored under — one of the six built-in
 * ContactType values, or any custom kind a host registers in `config('contacts.types')`
 * (the config file's own worked example is `whatsapp`). Kinds outside the six collapse to
 * `ContactType::Custom` for typing purposes, but the raw string is what the registry is
 * keyed by, so it is the raw string that must drive resolution.
 *
 * Resolution order for every attribute: the `contacts.types.<kind>` entry first, then the
 * ContactType fallback (which applies its own `contacts.types.<case>` override before its
 * built-in default). That composition is why this class only handles the registry lookup
 * and delegates the rest — a custom kind falls back to Custom's behaviour, and a built-in
 * kind keeps working exactly as before.
 */
final class ContactKind
{
    /**
     * The raw kind a ContactType or kind string names: the type's value, the string itself,
     * or `custom` for a blank one.
     */
    public static function of(ContactType|string $type): string
    {
        if ($type instanceof ContactType) {
            return $type->value;
        }

        return $type !== '' ? $type : ContactType::Custom->value;
    }

    /**
     * The `contacts.types.<kind>` overrides for a raw kind, or an empty array.
     *
     * @return array<string, mixed>
     */
    public static function overrides(string $kind): array
    {
        return ContactsConfig::type($kind);
    }

    /**
     * Human label for the kind, falling back to the type's own label.
     */
    public static function label(ContactType $type, ?string $kind = null): string
    {
        $configured = self::overrides($kind ?? $type->value)['label'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $type->label();
    }

    /**
     * Icon name for the kind, falling back to the type's own icon.
     */
    public static function icon(ContactType $type, ?string $kind = null): string
    {
        $configured = self::overrides($kind ?? $type->value)['icon'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $type->icon();
    }

    /**
     * Validation rules registered for the kind, falling back to the type's own rules.
     *
     * @return list<string>
     */
    public static function validationRules(ContactType $type, ?string $kind = null): array
    {
        $configured = self::overrides($kind ?? $type->value)['rules'] ?? null;

        if (is_array($configured) && $configured !== []) {
            /** @var list<string> $rules */
            $rules = array_values($configured);

            return $rules;
        }

        return $type->validationRules();
    }
}
