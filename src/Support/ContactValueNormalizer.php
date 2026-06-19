<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Support\Str;
use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * Pure, native normalization of contact values per kind.
 *
 * No external libraries: emails are lower-cased, phones are reduced to an
 * E.164-ish form, URLs gain a scheme, everything else is trimmed.
 */
final class ContactValueNormalizer
{
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

    private static function normalizePhone(string $value): string
    {
        $trimmed = trim($value);
        $hasPlus = str_starts_with($trimmed, '+');

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return $trimmed;
        }

        if (! $hasPlus) {
            $countryCode = config('contacts.default_country_code');

            if (is_string($countryCode) && $countryCode !== '') {
                $prefix = ltrim($countryCode, '+');

                return '+'.$prefix.$digits;
            }

            return $digits;
        }

        return '+'.$digits;
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
