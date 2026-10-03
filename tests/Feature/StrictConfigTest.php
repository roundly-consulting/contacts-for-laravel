<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
use RoundlyConsulting\Contacts\Support\ContactKind;
use RoundlyConsulting\Contacts\Support\ContactRules;
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
    'ttl' => ['CONTACTS_VERIFICATION_TTL', 'verification.ttl', 60],
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

/*
 | The verification style is the security-relevant read: a typo used to fall through to the
 | 6-digit numeric code, silently downgrading a host that asked for a 32-byte token.
 */
it('refuses a mistyped verification style instead of issuing a code (strict config)', function (mixed $style): void {
    config()->set('contacts.verification.style', $style);

    $contact = User::create()->addEmail('style@acme.test');

    expect(fn () => $contact->requestVerification())
        ->toThrow(InvalidConfigurationException::class, 'contacts.verification.style')
        ->and($contact->fresh()?->verification_token)->toBeNull();
})->with(['typo' => 'tokn', 'wrong case' => 'Token', 'blank' => '', 'not a string' => 1]);

it('issues a numeric code when the verification style is absent (strict config)', function (): void {
    config()->set('contacts.verification.style', null);

    $plain = User::create()->addEmail('absent@acme.test')->requestVerification();

    expect($plain)->toMatch('/^\d{6}$/');
});

it('refuses a junk or non-positive verification ttl (strict config)', function (mixed $ttl): void {
    config()->set('contacts.verification.ttl', $ttl);

    $contact = User::create()->addEmail('ttl@acme.test');

    expect(fn () => $contact->requestVerification())
        ->toThrow(InvalidConfigurationException::class, 'contacts.verification.ttl')
        ->and($contact->fresh()?->verification_token)->toBeNull();
})->with(['word' => 'five', 'decimal' => '5.5', 'blank' => '', 'zero' => '0', 'negative' => -1, 'over a year' => 525_601]);

it('reads a canonical ttl string and defaults an absent one to 60 minutes (strict config)', function (): void {
    Carbon::setTestNow('2026-10-03 12:00:00');

    config()->set('contacts.verification.ttl', ' 30 ');
    $short = User::create()->addEmail('short@acme.test');
    $short->requestVerification();

    config()->set('contacts.verification.ttl', null);
    $default = User::create()->addEmail('default@acme.test');
    $default->requestVerification();

    expect($short->fresh()?->verification_expires_at?->toDateTimeString())->toBe('2026-10-03 12:30:00')
        ->and($default->fresh()?->verification_expires_at?->toDateTimeString())->toBe('2026-10-03 13:00:00');

    Carbon::setTestNow();
});

it('refuses a non-string or blank table name (strict config)', function (mixed $table): void {
    config()->set('contacts.table', $table);

    expect(fn () => (new Contact)->getTable())
        ->toThrow(InvalidConfigurationException::class, 'contacts.table');
})->with(['array' => [['contacts']], 'blank' => '', 'whitespace' => '  ', 'integer' => 42]);

it('refuses a mistyped default country code (strict config)', function (mixed $code): void {
    config()->set('contacts.default_country_code', $code);

    expect(fn () => ContactType::Phone->normalize('0900 123 456'))
        ->toThrow(InvalidConfigurationException::class, 'contacts.default_country_code');
})->with([
    'letters' => 'abc',
    'ISO code' => 'SK',
    'stray letter' => '+42x1',
    'leading zero' => '00421',
    'too long' => '42100',
    'zero' => 0,
    'bool' => true,
    'array' => [['421']],
]);

it('reads an empty default country code as none (strict config)', function (): void {
    config()->set('contacts.default_country_code', '');

    expect(ContactType::Phone->normalize('0900 123 456'))->toBe('0900123456');
});

it('refuses a types registry that is not a kind map (strict config)', function (mixed $types): void {
    config()->set('contacts.types', $types);

    expect(fn () => ContactRules::kinds())
        ->toThrow(InvalidConfigurationException::class, 'contacts.types')
        ->and(fn () => ContactType::Email->label())
        ->toThrow(InvalidConfigurationException::class, 'contacts.types')
        ->and(fn () => ContactKind::label(ContactType::Custom, 'whatsapp'))
        ->toThrow(InvalidConfigurationException::class, 'contacts.types');
})->with([
    'a string' => 'whatsapp',
    'a list of kinds' => [['whatsapp']],
    'a blank kind' => [['' => ['label' => 'Blank']]],
    'a non-array definition' => [['whatsapp' => 'WhatsApp']],
    'a non-string label' => [['whatsapp' => ['label' => 42]]],
    'a blank icon' => [['whatsapp' => ['icon' => ' ']]],
    'rules as a string' => [['whatsapp' => ['rules' => 'required|string']]],
    'a non-string rule' => [['whatsapp' => ['rules' => ['required', 5]]]],
]);

it('refuses a relationship allow-list that is not an array (strict config)', function (): void {
    config()->set('contacts.relationship_kinds', 'works_at');

    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    expect(fn () => $person->relateTo($company, 'works_at'))
        ->toThrow(InvalidConfigurationException::class, 'contacts.relationship_kinds');
});

it('refuses an address model that is not the addresses model (strict config)', function (): void {
    $data = AddressData::make(city: 'Bratislava', street: 'Hlavna 1', postalCode: '81101', countryIso: 'SK');
    config()->set('addresses.model', User::class);

    expect(fn () => ContactAddressFormatter::fromData($data))
        ->toThrow(InvalidConfigurationException::class, 'addresses.model');
});

it('keeps the about section rendering on a malformed host config (strict config)', function (): void {
    config()->set('contacts.verification.style', 'tokn');
    config()->set('contacts.default_country_code', 'SK');
    config()->set('contacts.table', ['contacts']);
    config()->set('contacts.types', 'whatsapp');
    config()->set('contacts.relationship_kinds', 'works_at');

    expect('contacts')->toLeakNoSecrets(
        secrets: ['tokn', 'whatsapp', 'works_at'],
        mustRender: ['Verification', 'Default country code', 'Table', 'Custom types', 'Relationship kinds', 'INVALID'],
    );
});

it('reads absent registries as empty and free-form (strict config)', function (): void {
    config()->set('contacts.types', null);
    config()->set('contacts.relationship_kinds', null);

    $person = Contact::factory()->create();
    $person->relateTo(Contact::factory()->create(), 'anything_goes');

    expect(ContactRules::kinds())->toBe(array_map(static fn (ContactType $type): string => $type->value, ContactType::cases()))
        ->and($person->relationsOfKind('anything_goes'))->toHaveCount(1);
});
