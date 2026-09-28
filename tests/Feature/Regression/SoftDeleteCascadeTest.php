<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Contacts\Events\ContactDeleted;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Review finding: the `deleted` hook fired on a SOFT delete too, trashing the contact's
 * structured addresses and connections, and `restore()` never brought them back — the
 * README's "a deleted contact stays retrievable via withTrashed()" held for the row but
 * not for what hung off it.
 */
function contactWithGraph(): array
{
    $user = User::create();
    $contact = $user->addStructuredAddress(AddressData::make(city: 'Brno', street: 'Main 2', postalCode: '60200', countryIso: 'CZ', isPrimary: true));
    $peer = Contacts::for($user)->email('peer@corp.com')->add();
    $contact->connectTo($peer, ['x']);
    $peer->relateTo($contact, 'works_at');

    return [$contact, $peer];
}

it('brings the addresses and connections back on restore', function (): void {
    [$contact, $peer] = contactWithGraph();

    Contacts::delete($contact);

    expect(Address::query()->count())->toBe(0)
        ->and(Connection::query()->count())->toBe(0);

    Contact::withTrashed()->findOrFail($contact->getKey())->restore();

    $restored = $contact->fresh();

    expect($restored?->addresses()->count())->toBe(1)
        ->and($restored?->primaryAddress()?->city)->toBe('Brno')
        ->and($restored?->connections()->count())->toBe(1)
        ->and($restored?->connectors()->count())->toBe(1)
        ->and($peer->relationsOfKind('works_at')->modelKeys())->toBe([$contact->getKey()])
        ->and(Address::onlyTrashed()->count())->toBe(0);
});

it('leaves children that were deleted on their own before the contact', function (): void {
    [$contact] = contactWithGraph();
    $extra = $contact->addAddress(AddressData::make(city: 'Olomouc', street: 'Side 1', postalCode: '77900', countryIso: 'CZ'));

    Carbon::setTestNow(Carbon::now()->subMinute());
    $extra->delete();
    Carbon::setTestNow();

    Contacts::delete($contact);
    Contact::withTrashed()->findOrFail($contact->getKey())->restore();

    expect($contact->fresh()?->addresses()->pluck('city')->all())->toBe(['Brno'])
        ->and(Address::onlyTrashed()->count())->toBe(1);
});

it('removes the addresses and connections for good on a force delete', function (): void {
    [$contact] = contactWithGraph();

    Contacts::delete($contact);
    Contact::withTrashed()->findOrFail($contact->getKey())->forceDelete();

    expect(Address::withTrashed()->count())->toBe(0)
        ->and(Connection::withTrashed()->count())->toBe(0);
});

it('removes them on a direct force delete of a live contact too', function (): void {
    [$contact] = contactWithGraph();

    $contact->forceDelete();

    expect(Address::withTrashed()->count())->toBe(0)
        ->and(Connection::withTrashed()->count())->toBe(0);
});

it('keeps the restore stamp when an already deleted contact is deleted again', function (): void {
    [$contact] = contactWithGraph();

    Contacts::delete($contact);

    Carbon::setTestNow(Carbon::now()->addMinute());
    try {
        $trashed = Contact::withTrashed()->findOrFail($contact->getKey());
        Contacts::delete($trashed);
        $trashed->delete();
    } finally {
        Carbon::setTestNow();
    }

    Contact::withTrashed()->findOrFail($contact->getKey())->restore();

    expect($contact->fresh()?->addresses()->count())->toBe(1)
        ->and($contact->fresh()?->connections()->count())->toBe(1);
});

it('does not announce deleting an already deleted contact again', function (): void {
    [$contact] = contactWithGraph();
    Contacts::delete($contact);

    Event::fake([ContactDeleted::class]);
    Contacts::delete(Contact::withTrashed()->findOrFail($contact->getKey()));

    Event::assertNotDispatched(ContactDeleted::class);
});
