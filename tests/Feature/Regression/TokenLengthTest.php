<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Review finding: bcrypt reads only the first 72 bytes of what it hashes, so a
 * `token_length` above 36 bytes (72 hex characters) was only partly checked — a wrong
 * token sharing the first 72 characters was accepted. The configured length now has to fit.
 */
beforeEach(function (): void {
    config()->set('hashing.bcrypt.rounds', 4);
    config()->set('contacts.verification.style', 'token');
});

it('refuses a token_length whose hex form bcrypt would truncate', function (int $bytes): void {
    config()->set('contacts.verification.token_length', $bytes);

    Contacts::verification()->request(User::create()->addEmail('a@b.com'));
})->with([37, 40, 64])->throws(InvalidConfigurationException::class);

it('refuses a code_length bcrypt would truncate', function (): void {
    config()->set('contacts.verification.style', 'code');
    config()->set('contacts.verification.code_length', 73);

    Contacts::verification()->request(User::create()->addEmail('a@b.com'));
})->throws(InvalidConfigurationException::class);

it('checks every character of the longest allowed token', function (): void {
    config()->set('contacts.verification.token_length', 36);
    $contact = User::create()->addEmail('a@b.com');

    $token = Contacts::verification()->request($contact);
    $lastCharFlipped = substr($token, 0, -1).($token[71] === 'a' ? 'b' : 'a');

    expect(strlen($token))->toBe(72)
        ->and(fn () => Contacts::verification()->confirm($contact, $lastCharFlipped))->toThrow(InvalidVerificationToken::class)
        ->and(Contacts::verification()->confirm($contact, $token)->isVerified())->toBeTrue();
});

it('reads the lengths from env strings', function (): void {
    config()->set('contacts.verification.token_length', '8');

    expect(Contacts::verification()->request(User::create()->addEmail('a@b.com')))->toHaveLength(16);
});
