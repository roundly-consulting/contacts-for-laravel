<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Support\Str;
use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * Pure, native normalization of contact values per kind.
 *
 * No external libraries: emails are lower-cased, phones are reduced to an
 * E.164-ish form (national formats included), URLs gain a scheme, everything else is
 * trimmed.
 */
final class ContactValueNormalizer
{
    /**
     * Calling codes whose national numbers keep their leading 0 in international form
     * (Italy, San Marino): for them a leading 0 is part of the number, not a trunk prefix.
     */
    private const array LEADING_ZERO_KEPT = ['39', '378'];

    public static function normalize(ContactType $type, string $value): string
    {
        return match ($type) {
            ContactType::Email => self::normalizeEmail($value),
            ContactType::Phone => self::normalizePhone($value),
            ContactType::Url => self::normalizeUrl($value),
            default => trim($value),
        };
    }

    private static function normalizeEmail(string $value): string
    {
        return Str::lower(trim($value));
    }

    /**
     * Best-effort E.164: separators stripped, a `(0)` trunk marker after `+cc` dropped,
     * the `00` international prefix read as `+`, and — with `default_country_code` set — a
     * national number's trunk `0` replaced by the country code. Without a `+`, a `00` or a
     * configured country, the bare digits are kept.
     */
    private static function normalizePhone(string $value): string
    {
        $trimmed = trim($value);

        // '+44 (0)20 …' — the bracketed trunk 0 is dialled nationally only.
        $trimmed = preg_replace('/^(\+\d{1,3})\s*\(0\)/', '$1', $trimmed) ?? $trimmed;

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '+')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        $countryCode = self::defaultCountryCode();

        if ($countryCode === null) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && ! in_array($countryCode, self::LEADING_ZERO_KEPT, true)) {
            $digits = substr($digits, 1);
        }

        return '+'.$countryCode.$digits;
    }

    private static function defaultCountryCode(): ?string
    {
        $configured = config('contacts.default_country_code');

        if (! is_string($configured) && ! is_int($configured)) {
            return null;
        }

        $code = preg_replace('/\D+/', '', (string) $configured) ?? '';

        return $code === '' ? null : $code;
    }

    private static function normalizeUrl(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (! preg_match('#^[a-z][a-z0-9+.\-]*://#i', $trimmed)) {
            return 'https://'.$trimmed;
        }

        return $trimmed;
    }
}
