<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
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

/**
 * Chat review V-1 (owner answer #36): the structured address `add()` attaches is the
 * contact's own address, so it is attached as its primary — `primaryAddress()` finds it and
 * `formattedAddress()` renders it, edits included, instead of a stale mirrored `value`.
 */
it('attaches the structured address as the contact\'s primary address', function (): void {
    $user = User::create(['name' => 'Acme']);

    $built = Contacts::for($user)->structuredAddress([
        'city' => 'Vienna',
        'street' => 'Ring 3',
        'postalCode' => '1010',
        'countryIso' => 'AT',
    ])->add();
    $helper = $user->addStructuredAddress(AddressData::make(city: 'Kosice', street: 'Mlynska 2', postalCode: '04001', countryIso: 'SK'));

    expect($built->primaryAddress()?->city)->toBe('Vienna')
        ->and($helper->primaryAddress()?->city)->toBe('Kosice');

    $built->primaryAddress()?->update(['street' => 'Moved 5']);

    expect($built->formattedAddress())->toContain('Moved 5');
});

/**
 * Chat review C-3: `update()` ignored `ContactData::$address`. A blank value with an address
 * threw, a new address was dropped while the old one kept rendering, and an address on a
 * non-address kind was accepted although `add()` refuses it. It now mirrors `add()`.
 */
function kosice(): AddressData
{
    return AddressData::make(city: 'Kosice', street: 'Side 2', postalCode: '04001', countryIso: 'SK');
}

it('derives a blank updated value from the new structured address', function (): void {
    $contact = User::create()->addStructuredAddress(billing());

    $updated = Contacts::update($contact, new ContactData(ContactType::Address, '', address: kosice()));

    expect($updated->value)->toBe(ContactAddressFormatter::fromData(kosice()))
        ->and($updated->fresh()?->value)->toContain('Side 2');
});

it('replaces the structured address and renders the new one', function (): void {
    $contact = User::create()->addStructuredAddress(billing());
    $original = $contact->primaryAddress();

    $updated = Contacts::update($contact, new ContactData(ContactType::Address, 'Side 2, 04001 Kosice, SK', address: kosice()));

    expect($updated->formattedAddress())->toContain('Side 2')
        ->and($updated->formattedAddress())->not->toContain('Hlavna')
        ->and($updated->fresh()?->value)->toContain('Side 2')
        ->and($updated->addresses()->pluck('city')->all())->toBe(['Kosice'])
        ->and($updated->primaryAddress()?->is($original))->toBeTrue();
});

it('replaces a structured address attached before it was made primary', function (): void {
    $contact = User::create()->addStructuredAddress(billing());
    // A 1.0.x row: the structured address was attached without the primary flag.
    $contact->addresses()->update(['is_primary' => false]);

    $updated = Contacts::update($contact, new ContactData(ContactType::Address, '', address: kosice()));

    expect($updated->addresses()->pluck('city')->all())->toBe(['Kosice'])
        ->and($updated->formattedAddress())->toContain('Side 2');
});

it('attaches a structured address to a loose address contact on update', function (): void {
    $contact = User::create()->addAddressContact('somewhere loose');

    $updated = Contacts::update($contact, new ContactData(ContactType::Address, '', address: kosice()));

    expect($updated->addresses()->count())->toBe(1)
        ->and($updated->formattedAddress())->toContain('Side 2')
        ->and($updated->value)->toContain('Side 2');
});

it('refuses a structured address when updating a contact that is not an address', function (): void {
    $contact = User::create()->addEmail('a@x.test');

    expect(fn () => Contacts::update($contact, new ContactData(ContactType::Email, 'b@x.test', address: kosice())))
        ->toThrow(InvalidContactValue::class, 'only be attached to an address contact (got: email)');

    expect($contact->fresh()?->value)->toBe('a@x.test')
        ->and($contact->addresses()->count())->toBe(0);
});

/**
 * Owner answer #37: a value-only change rewords the text and keeps the structured address.
 */
it('keeps the structured address on a value-only update', function (): void {
    $contact = User::create()->addStructuredAddress(billing());

    $updated = Contacts::update($contact, new ContactData(ContactType::Address, 'Head office'));

    expect($updated->value)->toBe('Head office')
        ->and($updated->addresses()->pluck('city')->all())->toBe(['Bratislava'])
        ->and($updated->formattedAddress())->toContain('Hlavna 1');
});

it('writes the row and its structured address together, or neither', function (): void {
    $contact = User::create()->addStructuredAddress(billing());
    $stored = $contact->value;

    app()->instance(AddressManager::class, new class(app()) extends AddressManager
    {
        public function update(Address $address, AddressData $data): Address
        {
            throw new RuntimeException('address write failed');
        }
    });

    expect(fn () => Contacts::update($contact, new ContactData(ContactType::Address, '', label: 'Moved', address: kosice())))
        ->toThrow(RuntimeException::class, 'address write failed');

    expect($contact->fresh()?->value)->toBe($stored)
        ->and($contact->fresh()?->label)->toBeNull()
        ->and($contact->value)->toBe($stored)
        ->and($contact->label)->toBeNull()
        ->and($contact->isDirty())->toBeFalse()
        ->and($contact->addresses()->pluck('city')->all())->toBe(['Bratislava']);
});
