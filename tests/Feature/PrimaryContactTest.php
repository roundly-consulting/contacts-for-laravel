<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\SetPrimaryContactAction;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Facades\Contacts;
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

/**
 * Chat review C-14: promoting a soft-deleted contact demoted the live primary and flagged
 * the trashed row, leaving the kind with no live primary — `primaryEmail()` went null and
 * mail routing silently dropped.
 */
it('refuses to promote a trashed contact and keeps the live primary', function (): void {
    $user = User::create();
    $live = $user->addEmail('a@x.test');
    $trashed = $user->addEmail('b@x.test');
    $trashed->delete();

    expect(fn () => Contacts::setPrimary($trashed))
        ->toThrow(PrimaryContactConflict::class, 'A deleted contact cannot be primary.');

    expect($live->fresh()?->is_primary)->toBeTrue()
        ->and(Contact::withTrashed()->findOrFail($trashed->getKey())->is_primary)->toBeFalse()
        ->and($user->primaryEmail()?->is($live))->toBeTrue();
});
