<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('records adds without touching the database', function (): void {
    $fake = Contacts::fake();
    $user = User::create();

    $user->addEmail('a@b.com');

    $fake->assertAdded();
    $fake->assertAdded(fn (ContactData $data): bool => $data->type === ContactType::Email
        && $data->value === 'a@b.com');

    expect(Contact::query()->count())->toBe(0);
});

it('records verify and primary calls', function (): void {
    $fake = Contacts::fake();
    $contact = new Contact;

    Contacts::verify($contact);
    Contacts::setPrimary($contact);

    $fake->assertVerified($contact);
    $fake->assertPrimarySet($contact);
});

it('records sync and update and delete calls', function (): void {
    $fake = Contacts::fake();
    $user = User::create();
    $contact = new Contact;

    Contacts::sync($user, ContactType::Phone, [new ContactData(ContactType::Phone, '+421900000000')]);
    Contacts::update($contact, new ContactData(ContactType::Email, 'a@b.com'));
    Contacts::delete($contact);

    expect($fake->synced)->toHaveCount(1)
        ->and($fake->updated)->toHaveCount(1)
        ->and($fake->deleted)->toHaveCount(1);
});

it('asserts nothing was added', function (): void {
    $fake = Contacts::fake();

    $fake->assertNothingAdded();
});

it('asserts verify and primary without a specific contact', function (): void {
    $fake = Contacts::fake();
    $contact = new Contact;

    Contacts::verify($contact);
    Contacts::setPrimary($contact);

    $fake->assertVerified();
    $fake->assertPrimarySet();
});
