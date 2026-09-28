<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;

it('builds from an array with coercion', function (): void {
    $data = ContactData::fromArray([
        'type' => 'email',
        'value' => 'a@b.com',
        'label' => 'Work',
        'is_primary' => 1,
        'position' => '3',
        'meta' => ['source' => 'import'],
    ]);

    expect($data->type)->toBe(ContactType::Email)
        ->and($data->value)->toBe('a@b.com')
        ->and($data->label)->toBe('Work')
        ->and($data->isPrimary)->toBeTrue()
        ->and($data->position)->toBe(3)
        ->and($data->meta)->toBe(['source' => 'import']);
});

it('defaults to custom type and empty meta', function (): void {
    $data = ContactData::fromArray(['value' => 'x']);

    expect($data->type)->toBe(ContactType::Custom)
        ->and($data->meta)->toBe([])
        ->and($data->position)->toBeNull()
        ->and($data->label)->toBeNull();
});

it('accepts an enum instance as type', function (): void {
    $data = ContactData::fromArray(['type' => ContactType::Phone, 'value' => '+421900000000']);

    expect($data->type)->toBe(ContactType::Phone);
});

it('returns a normalized copy', function (): void {
    $data = new ContactData(type: ContactType::Email, value: '  Foo@BAR.com ');

    $normalized = $data->normalized();

    expect($normalized)->not->toBe($data)
        ->and($normalized->value)->toBe('foo@bar.com')
        ->and($normalized->type)->toBe(ContactType::Email);
});

it('carries a structured address from an array or a dto', function (): void {
    $fromArray = ContactData::fromArray([
        'type' => 'address',
        'address' => ['city' => 'Vienna', 'street' => 'Ring 3', 'postal_code' => '1010', 'country_iso' => 'AT'],
    ]);
    $dto = AddressData::make(
        city: 'Vienna',
        street: 'Ring 3',
        postalCode: '1010',
        countryIso: 'AT',
    );
    $fromDto = ContactData::fromArray(['type' => 'address', 'address' => $dto]);
    $without = ContactData::fromArray(['type' => 'address', 'address' => 'Ring 3']);

    expect($fromArray->address?->city)->toBe('Vienna')
        ->and($fromDto->address)->toBe($dto)
        ->and($fromDto->normalized()->address)->toBe($dto)
        ->and($fromDto->withValue('x')->value)->toBe('x')
        ->and($without->address)->toBeNull();
});
