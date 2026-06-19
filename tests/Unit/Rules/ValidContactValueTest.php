<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

it('passes a valid normalized email', function (): void {
    $validator = Validator::make(
        ['email' => 'A.Person@Example.COM'],
        ['email' => ['required', new ValidContactValue(ContactType::Email)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails an invalid email', function (): void {
    $validator = Validator::make(
        ['email' => 'not-an-email'],
        ['email' => [new ValidContactValue(ContactType::Email)]],
    );

    expect($validator->fails())->toBeTrue();
});

it('validates a schemeless url after normalization', function (): void {
    expect(ValidContactValue::passes(ContactType::Url, 'example.com'))->toBeTrue()
        ->and(ValidContactValue::passes(ContactType::Url, 'not a url'))->toBeFalse();
});

it('validates phone format', function (): void {
    expect(ValidContactValue::passes(ContactType::Phone, '+421 900 000 000'))->toBeTrue()
        ->and(ValidContactValue::passes(ContactType::Phone, '12'))->toBeFalse();
});

it('fails non-string values', function (): void {
    $validator = Validator::make(
        ['email' => ['array']],
        ['email' => [new ValidContactValue(ContactType::Email)]],
    );

    expect($validator->fails())->toBeTrue();
});
