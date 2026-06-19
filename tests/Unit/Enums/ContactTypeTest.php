<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;

it('resolves known and unknown types', function (): void {
    expect(ContactType::fromValueOrCustom('email'))->toBe(ContactType::Email)
        ->and(ContactType::fromValueOrCustom('whatsapp'))->toBe(ContactType::Custom)
        ->and(ContactType::fromValueOrCustom(null))->toBe(ContactType::Custom);
});

it('normalizes values per kind', function (): void {
    expect(ContactType::Email->normalize('  Foo@BAR.com '))->toBe('foo@bar.com')
        ->and(ContactType::Phone->normalize('+421 900 000 000'))->toBe('+421900000000')
        ->and(ContactType::Url->normalize('example.com'))->toBe('https://example.com')
        ->and(ContactType::Url->normalize('https://example.com'))->toBe('https://example.com')
        ->and(ContactType::Social->normalize('  @handle '))->toBe('@handle');
});

it('returns a translated label with english fallback', function (): void {
    expect(ContactType::Email->label())->toBe('Email')
        ->and(ContactType::Custom->label())->toBe('Other');
});

it('respects a config label override', function (): void {
    config()->set('contacts.types.email.label', 'Work mail');

    expect(ContactType::Email->label())->toBe('Work mail');
});

it('returns a default icon overridable by config', function (): void {
    expect(ContactType::Phone->icon())->toBe('phone');

    config()->set('contacts.types.phone.icon', 'device-phone-mobile');

    expect(ContactType::Phone->icon())->toBe('device-phone-mobile');
});

it('returns a default icon for every kind', function (): void {
    expect(ContactType::Email->icon())->toBe('envelope')
        ->and(ContactType::Phone->icon())->toBe('phone')
        ->and(ContactType::Address->icon())->toBe('map-pin')
        ->and(ContactType::Url->icon())->toBe('globe-alt')
        ->and(ContactType::Social->icon())->toBe('at-symbol')
        ->and(ContactType::Custom->icon())->toBe('identification');
});

it('falls back to ucfirst when no translation exists', function (): void {
    // Clear loaded lines and the package namespace so trans() returns the key.
    $translator = app('translator');
    $translator->setLoaded([]);
    $translator->addNamespace('contacts', __DIR__.'/__missing__');
    app()->setLocale('zz');

    // "custom" → English label is "Other", so ucfirst("custom") proves the fallback ran.
    expect(ContactType::Custom->label())->toBe('Custom');
});

it('returns validation rules per kind', function (): void {
    expect(ContactType::Email->validationRules())->toContain('email')
        ->and(ContactType::Url->validationRules())->toContain('url')
        ->and(ContactType::Address->validationRules())->toBe(['required', 'string']);
});

it('reads config validation rules for a kind', function (): void {
    config()->set('contacts.types.social.rules', ['required', 'string', 'min:2']);

    expect(ContactType::Social->validationRules())->toBe(['required', 'string', 'min:2']);
});
