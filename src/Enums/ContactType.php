<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Enums;

use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\ContactValueNormalizer;
use RoundlyConsulting\Enums\Helpers;

enum ContactType: string
{
    // Adds values()/labels()/options()/toOptions()/names()/validationRule() plus
    // is()/isNot()/isIn()/whenIs() and case lookups. The domain label() below is
    // intentionally kept: a class-defined method shadows the trait's plain alias,
    // so config/lang/icon-aware labelling still wins with no behaviour change.
    use Helpers;

    case Email = 'email';
    case Phone = 'phone';
    case Address = 'address';
    case Url = 'url';
    case Social = 'social';
    case Custom = 'custom';

    /**
     * Resolve a stored type string to a case, falling back to Custom for any
     * value outside the six canonical kinds (e.g. config-defined custom kinds).
     */
    public static function fromValueOrCustom(?string $type): self
    {
        if ($type === null) {
            return self::Custom;
        }

        return self::tryFrom($type) ?? self::Custom;
    }

    /**
     * Human label, translatable via contacts::types.* with an English fallback,
     * overridable per custom kind through config.
     */
    public function label(): string
    {
        $configured = config('contacts.types.'.$this->value.'.label');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $key = 'contacts::types.'.$this->value;
        $translated = trans($key);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        return ucfirst($this->value);
    }

    /**
     * Icon name string, overridable via config('contacts.types.<key>.icon').
     */
    public function icon(): string
    {
        $configured = config('contacts.types.'.$this->value.'.icon');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return match ($this) {
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::Address => 'map-pin',
            self::Url => 'globe-alt',
            self::Social => 'at-symbol',
            self::Custom => 'identification',
        };
    }

    /**
     * Illuminate validation rule strings for this kind. Custom/Social/Address
     * rules can be overridden through config('contacts.types.<key>.rules').
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $configured = config('contacts.types.'.$this->value.'.rules');

        if (is_array($configured) && $configured !== []) {
            /** @var list<string> $rules */
            $rules = array_values(array_filter($configured, 'is_string'));

            return $rules;
        }

        return match ($this) {
            self::Email => ['required', 'string', 'email'],
            self::Phone => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
            self::Url => ['required', 'string', 'url'],
            default => ['required', 'string'],
        };
    }

    /**
     * Ready-to-use FormRequest rules for a single value of this kind, ending
     * with the reusable ValidContactValue rule. Drop it straight into a request:
     *
     *     'email' => ContactType::Email->rules(),
     *
     * @return list<string|ValidContactValue>
     */
    public function rules(): array
    {
        return [...$this->validationRules(), new ValidContactValue($this)];
    }

    /**
     * Static convenience equivalent of {@see self::rules()}.
     *
     * @return list<string|ValidContactValue>
     */
    public static function rulesFor(self $type): array
    {
        return $type->rules();
    }

    /**
     * Canonical form of a value for this kind.
     */
    public function normalize(string $value): string
    {
        return ContactValueNormalizer::normalize($this, $value);
    }
}
