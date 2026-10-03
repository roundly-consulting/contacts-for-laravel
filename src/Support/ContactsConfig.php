<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The strict readers behind every contacts setting that is not a switch or a plain bounded
 * integer read at its one call site.
 *
 * An absent (null) key means the documented default. A present value of the wrong shape
 * throws InvalidConfigurationException naming the key: a typo never falls back silently.
 * That matters most for `verification.style`, where the old fallback turned a mistyped
 * `token` into the 6-digit numeric code — a quiet downgrade from 32 random bytes.
 *
 * @internal
 */
final class ContactsConfig
{
    /**
     * The verification styles: a numeric one-time code, or a random hex token.
     */
    public const string STYLE_CODE = 'code';

    public const string STYLE_TOKEN = 'token';

    /**
     * Minutes in a year — the longest verification TTL accepted.
     */
    private const int MAX_TTL_MINUTES = 525_600;

    private const string TYPES = 'contacts.types';

    /**
     * The configured table name, or null when none is configured (the model's own name).
     */
    public static function table(): ?string
    {
        return config('contacts.table') === null ? null : Config::requireString('contacts.table');
    }

    /**
     * `code` or `token`; absent means `code`.
     */
    public static function verificationStyle(): string
    {
        return Config::oneOf('contacts.verification.style', [self::STYLE_CODE, self::STYLE_TOKEN], self::STYLE_CODE);
    }

    /**
     * Minutes a verification token stays valid: 1 to a year, 60 when absent.
     */
    public static function verificationTtl(): int
    {
        return Config::integer('contacts.verification.ttl', 60, min: 1, max: self::MAX_TTL_MINUTES);
    }

    /**
     * Wrong guesses a verification token survives: 1–1000, 5 when absent.
     */
    public static function verificationMaxAttempts(): int
    {
        return Config::integer('contacts.verification.max_attempts', 5, min: 1, max: 1000);
    }

    /**
     * The digits of the default dialling prefix, or null when none is set (absent or empty).
     *
     * A prefix may be written `421`, `+421` or `1-264`; anything else — letters, an ISO code
     * such as `SK`, a leading zero, more than four digits — throws.
     */
    public static function defaultCountryCode(): ?string
    {
        $value = config('contacts.default_country_code');

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $digits = match (true) {
            is_int($value) => (string) $value,
            is_string($value) && preg_match('/^\s*\+?[\d\s-]+$/', $value) === 1 => preg_replace('/\D+/', '', $value) ?? '',
            default => '',
        };

        if (preg_match('/^[1-9]\d{0,3}$/', $digits) !== 1) {
            throw self::mustBe('contacts.default_country_code', 'a dialling code such as 421 or +421', $value);
        }

        return $digits;
    }

    /**
     * The `contacts.types` registry, validated: a map of kind => definition, each definition an
     * array whose optional `label` and `icon` are non-empty strings and whose optional `rules`
     * is a list of rule strings. Absent means no registered kinds.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function types(): array
    {
        $types = config(self::TYPES);

        if ($types === null) {
            return [];
        }

        if (! is_array($types)) {
            throw self::mustBe(self::TYPES, 'a kind => definition map', $types);
        }

        $validated = [];

        foreach ($types as $kind => $definition) {
            if (! is_string($kind) || trim($kind) === '') {
                throw self::mustBe(self::TYPES, 'a map keyed by kind name', $kind);
            }

            $validated[$kind] = self::definition(self::TYPES.'.'.$kind, $definition);
        }

        return $validated;
    }

    /**
     * The overrides registered for one kind, or an empty array.
     *
     * @return array<string, mixed>
     */
    public static function type(string $kind): array
    {
        return self::types()[$kind] ?? [];
    }

    /**
     * The relationship allow-list: a list of kinds or a kind => label map. Absent or empty
     * keeps kinds free-form.
     *
     * @return array<array-key, mixed>
     */
    public static function relationshipKinds(): array
    {
        $kinds = config('contacts.relationship_kinds');

        if ($kinds === null) {
            return [];
        }

        if (! is_array($kinds)) {
            throw self::mustBe('contacts.relationship_kinds', 'a list of kinds or a kind => label map', $kinds);
        }

        return $kinds;
    }

    /**
     * @return array<string, mixed>
     */
    private static function definition(string $key, mixed $definition): array
    {
        if (! is_array($definition)) {
            throw self::mustBe($key, 'an array of label, icon and rules', $definition);
        }

        foreach (['label', 'icon'] as $field) {
            $value = $definition[$field] ?? null;

            if ($value !== null && (! is_string($value) || trim($value) === '')) {
                throw InvalidConfigurationException::notAString($key.'.'.$field, $value);
            }
        }

        $rules = $definition['rules'] ?? null;

        if ($rules !== null && (! is_array($rules) || array_filter($rules, static fn (mixed $rule): bool => ! is_string($rule)) !== [])) {
            throw self::mustBe($key.'.rules', 'a list of rule strings', $rules);
        }

        /** @var array<string, mixed> $definition */
        return $definition;
    }

    private static function mustBe(string $key, string $expectation, mixed $value): InvalidConfigurationException
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException("Configuration value [{$key}] must be {$expectation}, [{$given}] given.");
    }
}
