<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * Builds validation rule arrays for host FormRequests that accept a repeatable
 * list of contact inputs (e.g. a "manage contacts" form posting an array of
 * {type, value} pairs).
 *
 * The available kinds are driven by the six built-in ContactType cases plus any
 * custom kinds registered under config('contacts.types').
 */
final class ContactRules
{
    /**
     * Rules for a repeatable `contacts.*` array of {type, value} pairs. The
     * value rule validates each entry against the configured rules for its
     * declared type via ValidContactValue.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public static function forArray(string $key = 'contacts'): array
    {
        return [
            $key => ['sometimes', 'array'],
            $key.'.*.type' => ['required', 'string', 'in:'.implode(',', self::kinds())],
            $key.'.*.value' => ['required', 'string'],
        ];
    }

    /**
     * Rules for a single contact value field of a known kind.
     *
     * @return list<string|ValidationRule>
     */
    public static function forValue(ContactType $type): array
    {
        return $type->rules();
    }

    /**
     * Every accepted kind string: the built-in cases plus configured custom
     * kinds.
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        $builtIn = array_map(static fn (ContactType $type): string => $type->value, ContactType::cases());

        $configured = config('contacts.types');
        $custom = is_array($configured) ? array_keys($configured) : [];
        $custom = array_values(array_filter($custom, 'is_string'));

        return array_values(array_unique([...$builtIn, ...$custom]));
    }
}
