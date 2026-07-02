<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;

it('exposes the backed values of every case via the enums trait', function (): void {
    expect(ContactType::values()->all())
        ->toBe(['email', 'phone', 'address', 'url', 'social', 'custom']);
});

it('exposes the case names via the enums trait', function (): void {
    expect(ContactType::names()->all())
        ->toBe(['Email', 'Phone', 'Address', 'Url', 'Social', 'Custom']);
});

it('builds option DTOs for select inputs', function (): void {
    $options = ContactType::options();

    expect($options)->toHaveCount(6)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('email')
        ->and($options->first()->name)->toBe('Email');
});

it('builds a value => label option map', function (): void {
    expect(ContactType::toOptions()->get('email'))->toBe('Email');
});

it('builds an in: validation rule from the backed values', function (): void {
    expect(ContactType::validationRule())
        ->toBe('in:email,phone,address,url,social,custom');
});

it('keeps the domain label() shadowing the trait alias', function (): void {
    // The config-aware domain label() must still win over the trait's plain alias.
    config()->set('contacts.types.email.label', 'Work mail');

    expect(ContactType::Email->label())->toBe('Work mail');
});

it('supports the trait comparison helpers', function (): void {
    expect(ContactType::Email->isIn([ContactType::Email, ContactType::Phone]))->toBeTrue()
        ->and(ContactType::Url->isIn([ContactType::Email, ContactType::Phone]))->toBeFalse()
        ->and(ContactType::Email->is(ContactType::Email))->toBeTrue()
        ->and(ContactType::Email->isNot(ContactType::Phone))->toBeTrue();
});

it('runs a whenIs branch for the matching case', function (): void {
    $ran = false;

    ContactType::Email->whenIs(ContactType::Email, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
