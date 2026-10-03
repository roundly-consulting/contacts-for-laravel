<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Rules\ValidContactEntryValue;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

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
     * Rules for a repeatable `contacts.*` array of {type, value} pairs. Each value is
     * normalized and validated against the rules of the kind its entry declares
     * (ValidContactEntryValue), including a custom kind's registered rules.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public static function forArray(string $key = 'contacts'): array
    {
        return [
            $key => ['sometimes', 'array'],
            $key.'.*.type' => ['required', 'string', 'in:'.implode(',', self::kinds())],
            $key.'.*.value' => ['required', 'string', new ValidContactEntryValue],
        ];
    }

    /**
     * Rules for a single contact value field of a known kind, or of a registered custom
     * kind when `$kind` is given.
     *
     * @return list<string|ValidationRule>
     */
    public static function forValue(ContactType $type, ?string $kind = null): array
    {
        return ValidContactValue::fieldRules($type, $kind);
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

        $custom = array_keys(ContactsConfig::types());

        return array_values(array_unique([...$builtIn, ...$custom]));
    }
}
