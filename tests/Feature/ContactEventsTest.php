<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\Actions\DeleteContactAction;
use RoundlyConsulting\Contacts\Actions\UpdateContactAction;
use RoundlyConsulting\Contacts\Actions\VerifyContactAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\ContactAdded;
use RoundlyConsulting\Contacts\Events\ContactDeleted;
use RoundlyConsulting\Contacts\Events\ContactUpdated;
use RoundlyConsulting\Contacts\Events\ContactVerified;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('dispatches ContactAdded and PrimaryContactChanged when adding the first contact', function (): void {
    Event::fake();
    $user = User::create();

    $user->addEmail('a@b.com');

    Event::assertDispatched(ContactAdded::class, 1);
    Event::assertDispatched(PrimaryContactChanged::class, 1);
});

it('dispatches ContactUpdated', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    Event::fake();
    app(UpdateContactAction::class)->execute($contact, new ContactData(ContactType::Email, 'c@d.com'));

    Event::assertDispatched(ContactUpdated::class, 1);
});

it('dispatches ContactDeleted', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    Event::fake();
    app(DeleteContactAction::class)->execute($contact);

    Event::assertDispatched(ContactDeleted::class, fn (ContactDeleted $event): bool => $event->contact->is($contact));
});

it('dispatches ContactVerified', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');

    Event::fake();
    app(VerifyContactAction::class)->execute($contact);

    Event::assertDispatched(ContactVerified::class, 1);
});
