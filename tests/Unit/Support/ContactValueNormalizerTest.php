<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\ContactValueNormalizer;

it('lowercases and trims emails', function (): void {
    expect(ContactValueNormalizer::normalize(ContactType::Email, ' A.Person@Example.COM '))
        ->toBe('a.person@example.com');
});

it('strips phone separators and keeps the plus', function (): void {
    expect(ContactValueNormalizer::normalize(ContactType::Phone, '+421 (900) 000-000'))
        ->toBe('+421900000000');
});

it('keeps bare digits when no plus and no country code', function (): void {
    config()->set('contacts.default_country_code', null);

    expect(ContactValueNormalizer::normalize(ContactType::Phone, '900 000 000'))
        ->toBe('900000000');
});

it('applies a default country code to bare numbers', function (): void {
    config()->set('contacts.default_country_code', '+421');

    expect(ContactValueNormalizer::normalize(ContactType::Phone, '900 000 000'))
        ->toBe('+421900000000');
});

it('returns the input when a phone has no digits', function (): void {
    expect(ContactValueNormalizer::normalize(ContactType::Phone, '   '))->toBe('');
});

it('prepends https to schemeless urls', function (): void {
    expect(ContactValueNormalizer::normalize(ContactType::Url, 'example.com'))
        ->toBe('https://example.com')
        ->and(ContactValueNormalizer::normalize(ContactType::Url, 'http://example.com'))
        ->toBe('http://example.com')
        ->and(ContactValueNormalizer::normalize(ContactType::Url, '  '))->toBe('');
});

it('trims other kinds', function (): void {
    expect(ContactValueNormalizer::normalize(ContactType::Address, '  12 Main St '))
        ->toBe('12 Main St');
});
