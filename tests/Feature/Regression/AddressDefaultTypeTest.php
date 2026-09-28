<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Regressions from the 2026-09-28 review. Each test names the defect it pins.
 */
it('lets an untyped structured address fall through to the host default_type', function (): void {
    config()->set('addresses.default_type', 'billing');

    $contact = Contacts::for(User::create())
        ->structuredAddress(['city' => 'Vienna', 'street' => 'Ring 3', 'postalCode' => '1010', 'countryIso' => 'AT'])
        ->add();

    expect($contact->addresses()->sole()->type)->toBe(AddressType::Billing);
});

it('still honours an explicit structured address type over default_type', function (): void {
    config()->set('addresses.default_type', 'billing');

    $contact = Contacts::for(User::create())
        ->structuredAddress(['city' => 'Vienna', 'street' => 'Ring 3', 'postalCode' => '1010', 'countryIso' => 'AT', 'type' => 'home'])
        ->add();

    expect($contact->addresses()->sole()->type)->toBe(AddressType::Home);
});
