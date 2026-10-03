<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | A typo in a host's env must fail loudly, never quietly become a number. The verification
 | lengths and attempt budget are read through the toolkit's strict `Config::integer()`,
 | but an `(int)` cast in the config file used to turn `=abc` into `0` and `=1.5` into `1`
 | before the reader ever saw it. The file now hands the raw string through.
 */

/**
 * Evaluate the shipped config file with one env variable set, the way a host boots it.
 *
 * @return array<string, mixed>
 */
function contactsConfigWithEnv(string $name, string $value): array
{
    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv("{$name}={$value}");

    try {
        /** @var array<string, mixed> */
        return require __DIR__.'/../../config/contacts.php';
    } finally {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }
}

dataset('contacts env integers', [
    'code length' => ['CONTACTS_VERIFICATION_CODE_LENGTH', 'verification.code_length', 6],
    'token length' => ['CONTACTS_VERIFICATION_TOKEN_LENGTH', 'verification.token_length', 32],
    'max attempts' => ['CONTACTS_VERIFICATION_MAX_ATTEMPTS', 'verification.max_attempts', 5],
]);

it('hands a mistyped env integer through raw (strict config)', function (string $name, string $path): void {
    expect(data_get(contactsConfigWithEnv($name, 'abc'), $path))->toBe('abc')
        ->and(data_get(contactsConfigWithEnv($name, '1.5'), $path))->toBe('1.5');
})->with('contacts env integers');

it('keeps the integer defaults when the env is unset', function (string $name, string $path, int $default): void {
    expect(data_get(contactsConfigWithEnv('CONTACTS_UNRELATED', 'x'), $path))->toBe($default);
})->with('contacts env integers');

it('refuses a non-integer verification code length (strict config)', function (): void {
    config()->set('contacts.verification.code_length', 'abc');

    $contact = User::create()->addEmail('typo@acme.test');

    expect(fn () => $contact->requestVerification())
        ->toThrow(InvalidConfigurationException::class, 'contacts.verification.code_length');
});

it('refuses a non-integer attempt budget (strict config)', function (): void {
    config()->set('contacts.verification.max_attempts', '1.5');

    $contact = User::create()->addEmail('budget@acme.test');
    $contact->requestVerification();

    expect(fn () => $contact->confirmVerification('000000'))
        ->toThrow(InvalidConfigurationException::class, 'contacts.verification.max_attempts');
});
