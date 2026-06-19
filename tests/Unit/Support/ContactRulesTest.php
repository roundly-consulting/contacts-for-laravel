<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\ContactRules;

it('builds array rules keyed by the given prefix', function (): void {
    $rules = ContactRules::forArray('phones');

    expect($rules)->toHaveKeys(['phones', 'phones.*.type', 'phones.*.value'])
        ->and($rules['phones.*.type'])->toContain('required');
});

it('includes configured custom kinds in the allowed type list', function (): void {
    config()->set('contacts.types', ['whatsapp' => ['label' => 'WhatsApp']]);

    expect(ContactRules::kinds())->toContain('whatsapp')
        ->and(ContactRules::kinds())->toContain('email');
});

it('forValue ends with the ValidContactValue rule', function (): void {
    $rules = ContactRules::forValue(ContactType::Email);

    expect(end($rules))->toBeInstanceOf(ValidContactValue::class);
});

it('validates a contacts array payload end to end', function (): void {
    $data = [
        'contacts' => [
            ['type' => 'email', 'value' => 'a@b.test'],
        ],
    ];

    expect(Validator::make($data, ContactRules::forArray())->passes())->toBeTrue();
});

it('rejects an unknown kind in a contacts array', function (): void {
    $data = [
        'contacts' => [
            ['type' => 'fax', 'value' => 'x'],
        ],
    ];

    expect(Validator::make($data, ContactRules::forArray())->fails())->toBeTrue();
});
