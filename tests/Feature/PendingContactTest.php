<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('builds an email contact with every setter', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)
        ->email('A@B.com')
        ->label('Work')
        ->name('Jane')
        ->category('staff')
        ->primary()
        ->meta(['source' => 'import'])
        ->add();

    expect($contact->type)->toBe(ContactType::Email)
        ->and($contact->value)->toBe('a@b.com')
        ->and($contact->label)->toBe('Work')
        ->and($contact->name)->toBe('Jane')
        ->and($contact->category)->toBe('staff')
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->meta->toArray())->toBe(['source' => 'import']);
});

it('builds url and address contacts', function (): void {
    $user = User::create();

    $url = Contacts::for($user)->url('example.com')->add();
    $address = Contacts::for($user)->address('12 Main St')->add();

    expect($url->value)->toBe('https://example.com')
        ->and($address->type)->toBe(ContactType::Address);
});

it('builds a contact via type and value setters', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)
        ->type('social')
        ->value('@handle')
        ->add();

    expect($contact->type)->toBe(ContactType::Social)
        ->and($contact->value)->toBe('@handle');

    $typed = Contacts::for($user)->type(ContactType::Phone)->value('+421900000000')->add();
    expect($typed->type)->toBe(ContactType::Phone);
});

/**
 * Chat review C-10: `structuredAddress()` defaulted the kind to address whenever the type
 * was still Custom — which an explicit `type('whatsapp')` also sets — so the result depended
 * on call order: the reverse order refused it, this one silently stored an address.
 */
it('refuses a structured address on an explicitly typed custom kind, in either order', function (): void {
    config()->set('contacts.types', ['whatsapp' => ['label' => 'WhatsApp']]);
    $user = User::create();
    $address = ['city' => 'Vienna', 'street' => 'Ring 3', 'postalCode' => '1010', 'countryIso' => 'AT'];

    expect(fn () => Contacts::for($user)->type('whatsapp')->structuredAddress($address)->add())
        ->toThrow(InvalidContactValue::class, '(got: whatsapp)')
        ->and(fn () => Contacts::for($user)->structuredAddress($address)->type('whatsapp')->add())
        ->toThrow(InvalidContactValue::class, '(got: whatsapp)')
        ->and(fn () => Contacts::for($user)->type(ContactType::Custom)->structuredAddress($address)->add())
        ->toThrow(InvalidContactValue::class, '(got: custom)')
        ->and(Contact::query()->count())->toBe(0);

    $defaulted = Contacts::for($user)->structuredAddress($address)->add();

    expect($defaulted->kind)->toBe('address');
});
