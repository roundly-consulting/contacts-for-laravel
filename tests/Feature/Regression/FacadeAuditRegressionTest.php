<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

/*
 * Regressions from the 2026-09 facade audit. Each failed on the previous code:
 * Contact::requestVerification()/confirmVerification() called the actions directly (the
 * fake never saw them and a real token was written), and a structured address was
 * attached AFTER the manager returned — under the fake, onto an unsaved Contact, so the
 * "fake" wrote a real Address row with no owner. A structured address on a non-address
 * contact overwrote (and so bypassed validation of) its real value, and the fluent builder
 * collapsed a registered custom kind (`->type('whatsapp')`) to `custom`.
 */

it('records a verification request made through the model', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $token = $contact->requestVerification();

    $fake->assertVerificationRequested($contact);
    expect($token)->toBe('fake-token')
        ->and($contact->fresh()->verification_token)->toBeNull();
});

it('records a verification confirmation made through the model', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $contact->confirmVerification('123456');

    $fake->assertVerificationConfirmed($contact);
    expect($contact->fresh()->verified_at)->toBeNull();
});

it('writes no address for a structured address added through the fake builder', function (): void {
    $user = User::create();
    Contacts::fake();

    $contact = Contacts::for($user)->structuredAddress([
        'city' => 'Vienna',
        'street' => 'Ring 3',
        'postalCode' => '1010',
        'countryIso' => 'AT',
    ])->add();

    expect(Address::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0)
        ->and($contact->value)->toContain('Ring 3');
});

it('writes no address for a structured address added through the fake trait', function (): void {
    $user = User::create();
    Contacts::fake();

    $user->addStructuredAddress(AddressData::make(
        city: 'Vienna',
        street: 'Ring 3',
        postalCode: '1010',
        countryIso: 'AT',
    ));

    expect(Address::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);
});

it('refuses a structured address on a contact that is not an address', function (): void {
    $user = User::create();

    expect(fn () => Contacts::for($user)->email('a@b.com')->structuredAddress(AddressData::make(
        city: 'Vienna',
        street: 'Ring 3',
        postalCode: '1010',
        countryIso: 'AT',
    ))->add())->toThrow(InvalidContactValue::class, 'only be attached to an address contact (got: email)');

    expect(Contact::query()->count())->toBe(0)
        ->and(Address::query()->count())->toBe(0);
});

it('keeps a registered custom kind added through the fluent builder', function (): void {
    config()->set('contacts.types', ['whatsapp' => ['label' => 'WhatsApp', 'rules' => ['required', 'string']]]);
    $user = User::create();

    $contact = Contacts::for($user)->type('whatsapp')->value('+421900123456')->add();

    expect($contact->kind)->toBe('whatsapp')
        ->and($contact->fresh()->kindLabel())->toBe('WhatsApp');
});
