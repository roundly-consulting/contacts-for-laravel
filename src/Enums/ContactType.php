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
     * The `contacts.types.<kind>` overrides for this kind, or an empty array.
     *
     * Reads the `contacts.types` registry as a whole and offsets into it in PHP,
     * rather than interpolating the kind into the config key
     * (`config('contacts.types.'.$this->value.'.label')`, as this enum used to do
     * three times over).
     *
     * That is not a style preference. An interpolated key is unverifiable: the config
     * contract cannot tell whether `contacts.types.<something>.label` is a key the
     * package ships or one it invented, so it flags the read rather than checking it —
     * and a package whose config keys can't be checked is exactly where dead config
     * hides. `contacts.types` is a host-extensible registry that ships empty, so the
     * only key here the contract can meaningfully pin is the section itself. Now it can.
     *
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        $types = config('contacts.types');

        if (! is_array($types)) {
            return [];
        }

        $configured = $types[$this->value] ?? null;

        return is_array($configured) ? $configured : [];
    }

    /**
     * Human label, translatable via contacts::types.* with an English fallback,
     * overridable per kind through config.
     */
    public function label(): string
    {
        $configured = $this->overrides()['label'] ?? null;

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
     * Icon name string, overridable through the `contacts.types` registry.
     */
    public function icon(): string
    {
        $configured = $this->overrides()['icon'] ?? null;

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
     * rules can be overridden through the `contacts.types` registry.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $configured = $this->overrides()['rules'] ?? null;

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
