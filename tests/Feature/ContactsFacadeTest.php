<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('adds a contact via the facade and fluent builder', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)
        ->phone('+421 900 000 000')
        ->label('Mobile')
        ->primary()
        ->add();

    expect($contact->value)->toBe('+421900000000')
        ->and($contact->type)->toBe(ContactType::Phone)
        ->and($contact->is_primary)->toBeTrue();
});

it('verifies and sets primary through the facade', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    Contacts::verify($contact);
    expect($contact->fresh()->verified_at)->not->toBeNull();

    $other = $user->addEmail('c@d.com');
    Contacts::setPrimary($other);

    expect($other->fresh()->is_primary)->toBeTrue()
        ->and($contact->fresh()->is_primary)->toBeFalse();
});

it('syncs contacts through the facade', function (): void {
    $user = User::create();

    $result = Contacts::sync($user, ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001'),
        new ContactData(ContactType::Phone, '+421900000002'),
    ]);

    expect($result)->toHaveCount(2);
});

it('adds via the manager add method', function (): void {
    $user = User::create();

    $contact = Contacts::add($user, new ContactData(ContactType::Email, 'a@b.com'));

    expect($contact->value)->toBe('a@b.com');
});

it('updates a contact through the facade', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    $updated = Contacts::update($contact, new ContactData(ContactType::Email, 'new@b.com'));

    expect($updated->value)->toBe('new@b.com');
});

it('deletes a contact through the facade', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    Contacts::delete($contact);

    expect($user->contacts()->count())->toBe(0);
});

it('exports a vcard through the facade', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com', label: 'Work');

    $vcard = Contacts::vCard($user);

    expect($vcard)->toContain('BEGIN:VCARD')
        ->toContain('FN:Jane Doe')
        ->toContain('EMAIL;TYPE=Work:jane@example.com');
});
