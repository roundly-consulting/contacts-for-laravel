<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

function billing(): AddressData
{
    return AddressData::make(
        city: 'Bratislava',
        street: 'Hlavna 1',
        postalCode: '81101',
        countryIso: 'SK',
        name: 'HQ',
        type: AddressType::Billing,
        isPrimary: true,
    );
}

it('attaches structured addresses to a contact and reads them back by type', function (): void {
    $contact = Contact::factory()->address()->create();

    $contact->addAddress(billing());
    $contact->addAddress(AddressData::make(
        city: 'Kosice',
        street: 'Mlynska 2',
        postalCode: '04001',
        countryIso: 'SK',
        type: AddressType::Home,
    ));

    expect($contact->addresses()->count())->toBe(2)
        ->and($contact->addressesOfType(AddressType::Billing))->toHaveCount(1)
        ->and($contact->addressesOfType(AddressType::Home))->toHaveCount(1);
});

it('promotes a primary address', function (): void {
    $contact = Contact::factory()->address()->create();

    $contact->addAddress(billing());

    expect($contact->primaryAddress())->not->toBeNull()
        ->and($contact->primaryAddress()->city)->toBe('Bratislava');
});

it('renders the primary structured address as the formatted address', function (): void {
    $contact = Contact::factory()->address()->create(['value' => 'loose one-liner']);

    $contact->addAddress(billing());

    expect($contact->formattedAddress())->toContain('Hlavna 1')
        ->and($contact->formattedAddress())->toContain('Bratislava');
});

it('falls back to the loose value when no structured address is attached', function (): void {
    $contact = Contact::factory()->address()->create(['value' => 'loose one-liner']);

    expect($contact->formattedAddress())->toBe('loose one-liner');
});

it('creates a contact and linked address in one builder call', function (): void {
    $user = User::create(['name' => 'Acme']);

    $contact = Contacts::for($user)
        ->address('fallback')
        ->structuredAddress([
            'city' => 'Vienna',
            'street' => 'Ring 3',
            'postalCode' => '1010',
            'countryIso' => 'AT',
            'type' => AddressType::Billing,
        ])
        ->add();

    expect($contact->type)->toBe(ContactType::Address)
        ->and($contact->addresses()->count())->toBe(1)
        ->and($contact->value)->toContain('Ring 3')
        ->and($contact->value)->toContain('Vienna');
});

it('creates an address-type contact from the owner helper', function (): void {
    $user = User::create(['name' => 'Acme']);

    $contact = $user->addStructuredAddress(billing(), label: 'Main');

    expect($contact->type)->toBe(ContactType::Address)
        ->and($contact->label)->toBe('Main')
        ->and($contact->addresses()->count())->toBe(1)
        ->and($contact->value)->toContain('Hlavna 1');
});

it('propagates an invalid country from the addresses package', function (): void {
    AddressData::make(
        city: 'Nowhere',
        street: 'Somewhere 1',
        postalCode: '00000',
        countryIso: 'ZZZZ',
    );
})->throws(InvalidCountryException::class);

it('cleans up attached addresses when the contact is deleted', function (): void {
    $contact = Contact::factory()->address()->create();
    $contact->addAddress(billing());

    $contact->delete();

    expect($contact->addresses()->count())->toBe(0);
});
