<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Capture an assertion failure raised inside a matcher. Pest's toThrow() does
 * not catch assertion failures, so we trap them by hand.
 */
function contactMatcherFails(Closure $matcher): bool
{
    try {
        $matcher();
    } catch (AssertionFailedError) {
        return true;
    }

    return false;
}

it('passes the contact matchers for an owner with contacts', function (): void {
    $user = User::create();
    $user->addEmail('hello@acme.test', primary: true);
    $user->addPhone('+421900000000', primary: true);

    expect($user)
        ->toHaveContactOfType(ContactType::Email)
        ->toHavePrimaryEmail('hello@acme.test')
        ->toHavePrimaryContact(ContactType::Phone, '+421900000000')
        ->toHavePrimaryContact('email');
});

it('matches a verified contact', function (): void {
    $user = User::create();
    $contact = $user->addEmail('verified@acme.test', primary: true);
    $contact->confirmVerification($contact->requestVerification());

    expect($user)->toHaveVerifiedContact('verified@acme.test');
});

it('fails toHaveContactOfType when none exists', function (): void {
    $user = User::create();

    expect(contactMatcherFails(fn () => expect($user)->toHaveContactOfType(ContactType::Email)))->toBeTrue();
});

it('fails toHavePrimaryEmail with a mismatched value', function (): void {
    $user = User::create();
    $user->addEmail('one@acme.test', primary: true);

    expect(contactMatcherFails(fn () => expect($user)->toHavePrimaryEmail('two@acme.test')))->toBeTrue();
});

it('fails toHavePrimaryContact when none is primary', function (): void {
    $user = User::create();

    expect(contactMatcherFails(fn () => expect($user)->toHavePrimaryContact(ContactType::Phone)))->toBeTrue();
});

it('fails toHaveVerifiedContact when unverified', function (): void {
    $user = User::create();
    $user->addEmail('pending@acme.test', primary: true);

    expect(contactMatcherFails(fn () => expect($user)->toHaveVerifiedContact('pending@acme.test')))->toBeTrue();
});
