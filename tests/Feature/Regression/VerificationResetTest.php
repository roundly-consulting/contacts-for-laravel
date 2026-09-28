<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * CRITICAL review finding: a verification proved ownership of the OLD value, yet it
 * survived a value change — `update()` overwrote `value` and left `verified_at` and the
 * pending token alone, so a verified address could be swapped for an attacker's and stay
 * verified, and a token sent to the old address could confirm the new one.
 */
it('drops the verification when the value changes', function (): void {
    $contact = User::create()->addEmail('victim@corp.com');
    Contacts::verification()->markVerified($contact);

    $updated = Contacts::update($contact, new ContactData(ContactType::Email, 'attacker@evil.com'));

    expect($updated->isVerified())->toBeFalse()
        ->and($updated->fresh()?->verified_at)->toBeNull();
});

it('refuses a token issued for the old value once the value changed', function (): void {
    $contact = User::create()->addEmail('old@corp.com');
    $token = Contacts::verification()->request($contact);

    Contacts::update($contact, new ContactData(ContactType::Email, 'new@evil.com'));

    expect(fn () => Contacts::verification()->confirm($contact->fresh() ?? $contact, $token))
        ->toThrow(InvalidVerificationToken::class);
    expect(fn () => Contacts::verification()->confirm($contact, $token))
        ->toThrow(InvalidVerificationToken::class);
    expect($contact->fresh()?->isVerified())->toBeFalse()
        ->and($contact->fresh()?->verification_token)->toBeNull()
        ->and($contact->fresh()?->verification_expires_at)->toBeNull();
});

it('drops the verification when the kind changes', function (): void {
    $contact = User::create()->addContact(ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456']));
    Contacts::verification()->markVerified($contact);

    $updated = Contacts::update($contact, new ContactData(ContactType::Phone, '+421900123456'));

    expect($updated->isVerified())->toBeFalse();
});

it('keeps the verification when the value is unchanged after normalization', function (): void {
    $contact = User::create()->addEmail('same@corp.com');
    Contacts::verification()->markVerified($contact);

    $updated = Contacts::update($contact, new ContactData(ContactType::Email, ' SAME@corp.com ', label: 'Work'));

    expect($updated->isVerified())->toBeTrue()
        ->and($updated->label)->toBe('Work');
});

it('drops the verification on a low-level value write too', function (): void {
    $contact = User::create()->addEmail('victim@corp.com');
    Contacts::verification()->markVerified($contact);

    $contact->update(['value' => 'attacker@evil.com']);

    expect($contact->fresh()?->isVerified())->toBeFalse();
});

it('honours a verification set explicitly in the same write', function (): void {
    $contact = User::create()->addEmail('import@corp.com');

    $contact->update(['value' => 'imported@corp.com', 'verified_at' => now()]);

    expect($contact->fresh()?->isVerified())->toBeTrue();
});
