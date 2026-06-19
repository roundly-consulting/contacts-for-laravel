<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * Reusable validation rule host apps can apply to a contact value field.
 *
 * Validates the normalized value against the rule set for the given kind.
 */
final class ValidContactValue implements ValidationRule
{
    public function __construct(
        private readonly ContactType $type,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('contacts::errors.invalid_value', ['type' => $this->type->value]));

            return;
        }

        if (! self::passes($this->type, $value)) {
            $fail(__('contacts::errors.invalid_value', ['type' => $this->type->value]));
        }
    }

    public static function passes(ContactType $type, string $value): bool
    {
        $normalized = $type->normalize($value);

        return Validator::make(
            ['value' => $normalized],
            ['value' => $type->validationRules()],
        )->passes();
    }
}
