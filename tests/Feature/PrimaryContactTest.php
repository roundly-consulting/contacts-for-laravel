<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\SetPrimaryContactAction;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('keeps at most one primary per kind and owner', function (): void {
    $user = User::create();

    $first = $user->addEmail('a@b.com');
    expect($first->is_primary)->toBeTrue();

    $second = $user->addEmail('c@d.com', primary: true);

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($user->contacts()->ofType(ContactType::Email)->primary()->count())->toBe(1);
});

it('isolates primaries between kinds', function (): void {
    $user = User::create();

    $email = $user->addEmail('a@b.com');
    $phone = $user->addPhone('+421900000000');

    expect($email->fresh()->is_primary)->toBeTrue()
        ->and($phone->fresh()->is_primary)->toBeTrue();
});

it('throws when require_owner_for_primary and no owner', function (): void {
    config()->set('contacts.require_owner_for_primary', true);

    $contact = Contact::factory()->email()->create(['owner_id' => null, 'owner_type' => null]);

    app(SetPrimaryContactAction::class)->execute($contact);
})->throws(PrimaryContactConflict::class);

it('allows owner-less primary by default', function (): void {
    $contact = Contact::factory()->email()->create(['owner_id' => null, 'owner_type' => null]);

    $result = app(SetPrimaryContactAction::class)->execute($contact);

    expect($result->is_primary)->toBeTrue();
});
