<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\Actions\SyncContactsAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('creates updates and deletes to match the given set', function (): void {
    $user = User::create();
    $add = app(AddContactAction::class);

    $keep = $add->execute($user, new ContactData(ContactType::Phone, '+421900000001'));
    $add->execute($user, new ContactData(ContactType::Phone, '+421900000002'));

    $result = app(SyncContactsAction::class)->execute($user, ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001', label: 'Mobile'),
        new ContactData(ContactType::Phone, '+421900000003'),
    ]);

    expect($result)->toHaveCount(2)
        ->and($user->contacts()->ofType(ContactType::Phone)->count())->toBe(2);

    $keep->refresh();
    expect($keep->label)->toBe('Mobile');

    $values = $user->contacts()->ofType(ContactType::Phone)->pluck('value')->all();
    expect($values)->toContain('+421900000001')
        ->toContain('+421900000003')
        ->not->toContain('+421900000002');
});

it('assigns positions by input order', function (): void {
    $user = User::create();

    $result = app(SyncContactsAction::class)->execute($user, ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001'),
        new ContactData(ContactType::Phone, '+421900000002'),
    ]);

    expect($result->first()->position)->toBe(0)
        ->and($result->last()->position)->toBe(1);
});

/**
 * Chat review C-4: an address-only item (blank value + structured address) normalized to ''
 * and never matched the stored render, so every sync deleted the contact and re-added it —
 * a new id, the verification lost, its address and connections cascade-trashed.
 */
it('keeps a structured address contact across repeated syncs', function (): void {
    $user = User::create();
    $items = fn (): array => [new ContactData(ContactType::Address, '', label: 'HQ', address: AddressData::make(
        city: 'Bratislava',
        street: 'Hlavna 1',
        postalCode: '81101',
        countryIso: 'SK',
    ))];

    $first = Contacts::for($user)->sync(ContactType::Address, $items())->sole();
    Contacts::verification()->markVerified($first);
    $addressId = $first->primaryAddress()?->getKey();

    $second = Contacts::for($user)->sync(ContactType::Address, $items())->sole();

    expect($second->getKey())->toBe($first->getKey())
        ->and($second->fresh()?->isVerified())->toBeTrue()
        ->and(Contact::onlyTrashed()->count())->toBe(0)
        ->and($second->addresses()->pluck('id')->all())->toBe([$addressId])
        ->and(Address::onlyTrashed()->count())->toBe(0);
});
