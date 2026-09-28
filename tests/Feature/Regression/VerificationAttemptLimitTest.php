<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Exceptions\VerificationAttemptsExceeded;
use RoundlyConsulting\Contacts\Exceptions\VerificationExpired;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Review finding: a 6-digit code (10^6 space, 60-minute TTL) could be guessed without
 * limit — wrong guesses were neither counted nor voided the token, so the whole space was
 * searchable within the TTL.
 */
beforeEach(function (): void {
    config()->set('hashing.bcrypt.rounds', 4);
});

function aWrongCode(string $right): string
{
    return $right === '000000' ? '111111' : '000000';
}

function guessWrong(Contact $contact, string $right, int $times): void
{
    for ($i = 0; $i < $times; $i++) {
        try {
            Contacts::verification()->confirm($contact, aWrongCode($right));
        } catch (InvalidVerificationToken) {
            // expected
        }
    }
}

it('voids the code after max_attempts wrong guesses', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);

    guessWrong($contact, $code, 5);

    expect(fn () => Contacts::verification()->confirm($contact, $code))->toThrow(InvalidVerificationToken::class)
        ->and($contact->fresh()?->isVerified())->toBeFalse()
        ->and($contact->fresh()?->verification_token)->toBeNull();
});

it('reports the guess that spends the last attempt', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);

    guessWrong($contact, $code, 4);

    expect(fn () => Contacts::verification()->confirm($contact, aWrongCode($code)))
        ->toThrow(VerificationAttemptsExceeded::class);
});

it('still accepts the right code within the budget', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);

    guessWrong($contact, $code, 4);

    expect(Contacts::verification()->confirm($contact, $code)->isVerified())->toBeTrue()
        ->and($contact->fresh()?->verification_attempts)->toBe(0);
});

it('counts attempts per token: a new request restores the budget', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $first = Contacts::verification()->request($contact);
    guessWrong($contact, $first, 5);

    $second = Contacts::verification()->request($contact);

    expect($contact->fresh()?->verification_attempts)->toBe(0)
        ->and(Contacts::verification()->confirm($contact, $second)->isVerified())->toBeTrue();
});

it('reads max_attempts from config, env strings included', function (): void {
    config()->set('contacts.verification.max_attempts', '2');
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);

    guessWrong($contact, $code, 2);

    expect(fn () => Contacts::verification()->confirm($contact, $code))->toThrow(InvalidVerificationToken::class);
});

it('counts in the database, so a stale copy cannot keep guessing', function (): void {
    // The interleaving of parallel requests: each loads the contact before any of the
    // others' guesses land. The budget lives in one conditional UPDATE, not in memory.
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);
    $stale = Contact::query()->findOrFail($contact->getKey());

    guessWrong($contact, $code, 5);

    expect($stale->verification_token)->not->toBeNull()
        ->and(fn () => Contacts::verification()->confirm($stale, $code))->toThrow(InvalidVerificationToken::class)
        ->and($contact->fresh()?->isVerified())->toBeFalse();
});

it('refuses a stale copy whose token was voided by a value change', function (): void {
    $contact = User::create()->addEmail('old@corp.com');
    $code = Contacts::verification()->request($contact);
    $stale = Contact::query()->findOrFail($contact->getKey());

    Contacts::update($contact, new ContactData(ContactType::Email, 'new@evil.com'));

    expect(fn () => Contacts::verification()->confirm($stale, $code))->toThrow(InvalidVerificationToken::class)
        ->and($contact->fresh()?->isVerified())->toBeFalse();
});

it('does not spend an attempt on an expired code', function (): void {
    config()->set('contacts.verification.ttl', 10);
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);

    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    try {
        expect(fn () => Contacts::verification()->confirm($contact, aWrongCode($code)))->toThrow(VerificationExpired::class)
            ->and($contact->fresh()?->verification_attempts)->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('voids a token whose budget was already spent under a lowered limit', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $code = Contacts::verification()->request($contact);
    guessWrong($contact, $code, 3);

    config()->set('contacts.verification.max_attempts', 2);

    expect(fn () => Contacts::verification()->confirm($contact, $code))->toThrow(VerificationAttemptsExceeded::class)
        ->and($contact->fresh()?->verification_token)->toBeNull();
});

it('does not verify when the token is replaced between the check and the write', function (): void {
    $contact = User::create()->addEmail('old@corp.com');
    $code = Contacts::verification()->request($contact);

    // The interleaving: a parallel request swaps the value (voiding the token) after this
    // request's hash comparison succeeded but before its write lands.
    Hash::shouldReceive('check')->once()->andReturnUsing(function () use ($contact): bool {
        Contact::query()->findOrFail($contact->getKey())->update(['value' => 'new@evil.com']);

        return true;
    });

    expect(fn () => Contacts::verification()->confirm($contact, $code))->toThrow(InvalidVerificationToken::class);

    $stored = $contact->fresh();

    expect($stored?->value)->toBe('new@evil.com')
        ->and($stored?->isVerified())->toBeFalse();
});
