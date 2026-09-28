<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\ContactRules;

/**
 * Validates the `value` of one `{type, value}` entry in a repeatable list against the
 * kind its sibling `type` declares — `contacts.3.value` against `contacts.3.type` — with
 * the same normalization and rules as {@see ValidContactValue}. An entry whose type is
 * missing or not an accepted kind is left to the type rule, which reports it.
 */
final class ValidContactEntryValue implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    public function __construct(
        private readonly string $typeField = 'type',
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $kind = Arr::get($this->data, Str::beforeLast($attribute, '.').'.'.$this->typeField);

        if (! is_string($kind) || ! in_array($kind, ContactRules::kinds(), true)) {
            return;
        }

        (new ValidContactValue(ContactType::fromValueOrCustom($kind), $kind))->validate($attribute, $value, $fail);
    }
}
