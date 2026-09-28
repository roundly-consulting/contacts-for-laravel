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
 * Validates the NORMALIZED value — what the package would store — against the rule set
 * for the given kind, and caps it at the `value` column's length. Pass `$kind` to validate
 * against a custom kind registered in `config('contacts.types')` — without it, a custom
 * kind validates against the plain `Custom` defaults and the host's registered rules
 * never run.
 */
final class ValidContactValue implements ValidationRule
{
    /**
     * The `value` column is a varchar(255); a longer value would fail on pgsql and strict
     * MySQL (and be silently kept whole by sqlite).
     */
    public const int MAX_LENGTH = 255;

    /**
     * Rules that describe the FIELD rather than the value's format: whether it must be
     * present, may be null, or is a string. They stay on the raw input; everything else
     * runs inside this rule, against the normalized value.
     */
    private const string FIELD_RULE = '/^(bail|sometimes|nullable|filled|string|(required|present|missing|exclude|prohibited)(_\w+)?)(:|$)/i';

    public function __construct(
        private readonly ContactType $type,
        private readonly ?string $kind = null,
    ) {}

    /**
     * Ready-to-use rules for a form field holding one raw value of a kind: the kind's
     * field rules (`required`, `nullable`, …) plus this rule, which runs the format rules
     * on the normalized value. `'+421 900 000 000'`, `'example.com'` and
     * `' A.Person@Example.COM '` pass, exactly as `addPhone()` / `addUrl()` / `addEmail()`
     * accept them.
     *
     * @return list<string|ValidationRule>
     */
    public static function fieldRules(ContactType $type, ?string $kind = null): array
    {
        $field = array_values(array_filter(
            ContactKind::validationRules($type, $kind),
            static fn (string $rule): bool => preg_match(self::FIELD_RULE, $rule) === 1,
        ));

        return [...$field, new self($type, $kind)];
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($this->type, $value, $this->kind)) {
            $fail(__('contacts::errors.invalid_value', ['type' => $this->kind ?? $this->type->value]));
        }
    }

    public static function passes(ContactType $type, string $value, ?string $kind = null): bool
    {
        $normalized = $type->normalize($value);

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            return false;
        }

        return Validator::make(
            ['value' => $normalized],
            ['value' => ContactKind::validationRules($type, $kind)],
        )->passes();
    }
}
