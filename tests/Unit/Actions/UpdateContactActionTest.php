<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\Actions\UpdateContactAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('updates and normalizes a contact', function (): void {
    $user = User::create();
    $contact = app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'a@b.com'));

    $updated = app(UpdateContactAction::class)->execute(
        $contact,
        new ContactData(type: ContactType::Email, value: '  NEW@B.com ', label: 'Work'),
    );

    expect($updated->value)->toBe('new@b.com')
        ->and($updated->label)->toBe('Work');
});

it('promotes to primary when requested', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();
    $contact = app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'a@b.com'));

    expect($contact->is_primary)->toBeFalse();

    $updated = app(UpdateContactAction::class)->execute(
        $contact,
        new ContactData(type: ContactType::Email, value: 'a@b.com', isPrimary: true),
    );

    expect($updated->is_primary)->toBeTrue();
});

it('updates name and position when provided', function (): void {
    $user = User::create();
    $contact = app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'a@b.com'));

    $updated = app(UpdateContactAction::class)->execute(
        $contact,
        new ContactData(type: ContactType::Email, value: 'a@b.com', name: 'Renamed', position: 5),
    );

    expect($updated->name)->toBe('Renamed')
        ->and($updated->position)->toBe(5);
});

it('throws on an invalid updated value', function (): void {
    $user = User::create();
    $contact = app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'a@b.com'));

    app(UpdateContactAction::class)->execute(
        $contact,
        new ContactData(type: ContactType::Email, value: 'nope'),
    );
})->throws(InvalidContactValue::class);
