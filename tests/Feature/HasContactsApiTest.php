<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
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
