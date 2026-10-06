<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\Company;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('adds typed contacts via trait sugar', function (): void {
    $user = User::create();

    $user->addEmail('A.Person@Example.COM ', label: 'Work', primary: true);
    $user->addPhone('+421 900 000 000', label: 'Mobile');
    $user->addUrl('example.com');
    $user->addAddress('12 Main St');

    expect($user->primaryEmail()?->value)->toBe('a.person@example.com')
        ->and($user->contactsOfType(ContactType::Url)->first()?->value)->toBe('https://example.com')
        ->and($user->contactsOfType('address')->first()?->value)->toBe('12 Main St');
});

it('adds a contact from a DTO', function (): void {
    $user = User::create();

    $contact = $user->addContact(new ContactData(ContactType::Email, 'a@b.com'));

    expect($contact->value)->toBe('a@b.com');
});

it('returns primary contacts per kind', function (): void {
    $user = User::create();

    $user->addEmail('a@b.com', primary: true);
    $user->addPhone('+421900000000', primary: true);

    expect($user->primaryEmail()?->value)->toBe('a@b.com')
        ->and($user->primaryPhone()?->value)->toBe('+421900000000');
});

it('returns ordered collections of a type', function (): void {
    $user = User::create();

    $user->addPhone('+421900000001');
    $user->addPhone('+421900000002');

    $phones = $user->contactsOfType(ContactType::Phone);

    expect($phones)->toBeInstanceOf(EloquentCollection::class)
        ->and($phones->pluck('value')->all())->toBe(['+421900000001', '+421900000002']);
});

it('returns null when no primary exists', function (): void {
    $user = User::create();

    expect($user->primaryEmail())->toBeNull();
});

/**
 * Chat review C-2: HasContacts::addAddress(string) and HasAddresses::addAddress(AddressData)
 * collide — a model using both traits is a PHP fatal at class load. `addAddressContact()` is
 * the collision-free name; the documented `insteadof` lets such a model load and use both.
 */
it('loads a model using HasContacts and HasAddresses with the documented workaround', function (): void {
    $company = Company::create(['name' => 'Acme']);

    $contact = $company->addAddressContact('12 Main St', label: 'HQ', primary: true);
    $address = $company->addAddress(AddressData::make(city: 'Vienna', street: 'Ring 3', postalCode: '1010', countryIso: 'AT'));

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->type)->toBe(ContactType::Address)
        ->and($contact->value)->toBe('12 Main St')
        ->and($contact->label)->toBe('HQ')
        ->and($contact->is_primary)->toBeTrue()
        ->and($address)->toBeInstanceOf(Address::class)
        ->and($company->addresses()->count())->toBe(1)
        ->and($company->contactsOfType(ContactType::Address)->modelKeys())->toBe([$contact->getKey()]);
});

it('keeps addAddress() as a deprecated alias of addAddressContact()', function (): void {
    $user = User::create();
    $fake = Contacts::fake();

    $user->addAddress('12 Main St', label: 'Home', primary: true);

    $fake->assertAdded(fn (ContactData $data): bool => $data->type === ContactType::Address
        && $data->value === '12 Main St'
        && $data->label === 'Home'
        && $data->isPrimary);
    expect((string) (new ReflectionMethod(User::class, 'addAddress'))->getDocComment())->toContain('@deprecated');
});
