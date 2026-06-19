<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('adds a normalized contact', function (): void {
    $user = User::create();

    $contact = app(AddContactAction::class)->execute(
        $user,
        new ContactData(type: ContactType::Email, value: '  A@B.com '),
    );

    expect($contact->value)->toBe('a@b.com')
        ->and($contact->type)->toBe(ContactType::Email)
        ->and($contact->owner->id)->toBe($user->id);
});

it('auto-sets the first contact of a kind as primary', function (): void {
    $user = User::create();

    $contact = app(AddContactAction::class)->execute(
        $user,
        new ContactData(type: ContactType::Email, value: 'a@b.com'),
    );

    expect($contact->is_primary)->toBeTrue();
});

it('does not auto-primary when disabled', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();

    $contact = app(AddContactAction::class)->execute(
        $user,
        new ContactData(type: ContactType::Email, value: 'a@b.com'),
    );

    expect($contact->is_primary)->toBeFalse();
});

it('increments position for subsequent contacts of a kind', function (): void {
    $user = User::create();
    $action = app(AddContactAction::class);

    $first = $action->execute($user, new ContactData(ContactType::Phone, '+421900000001'));
    $second = $action->execute($user, new ContactData(ContactType::Phone, '+421900000002'));

    expect($first->position)->toBe(0)
        ->and($second->position)->toBe(1);
});

it('throws on an invalid value', function (): void {
    $user = User::create();

    app(AddContactAction::class)->execute(
        $user,
        new ContactData(type: ContactType::Email, value: 'nope'),
    );
})->throws(InvalidContactValue::class);
