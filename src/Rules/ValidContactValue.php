<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\ContactKind;

/**
 * Reusable validation rule host apps can apply to a contact value field.
 *
 * Validates the normalized value against the rule set for the given kind. Pass `$kind` to
 * validate against a custom kind registered in `config('contacts.types')` — without it, a
 * custom kind validates against the plain `Custom` defaults and the host's registered rules
 * never run.
 */
final class ValidContactValue implements ValidationRule
{
    public function __construct(
        private readonly ContactType $type,
        private readonly ?string $kind = null,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('contacts::errors.invalid_value', ['type' => $this->kind ?? $this->type->value]));

            return;
        }

        if (! self::passes($this->type, $value, $this->kind)) {
            $fail(__('contacts::errors.invalid_value', ['type' => $this->kind ?? $this->type->value]));
        }
    }

    public static function passes(ContactType $type, string $value, ?string $kind = null): bool
    {
        $normalized = $type->normalize($value);

        return Validator::make(
            ['value' => $normalized],
            ['value' => ContactKind::validationRules($type, $kind)],
        )->passes();
    }
}
