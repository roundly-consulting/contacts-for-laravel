<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\ContactRules;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Review findings: `ContactType::rules()` ran the kind's format rules on the RAW input, so
 * a FormRequest refused exactly the values `addPhone()` / `addUrl()` / `addEmail()` accept;
 * `ContactRules::forArray()` never validated values per type at all; and nothing capped a
 * value at the varchar(255) column it is stored in.
 */
function passesRules(mixed $value, array $rules): bool
{
    return Validator::make(['field' => $value], ['field' => $rules])->passes();
}

it('validates the normalized value in ContactType::rules()', function (): void {
    expect(passesRules('+421 900 000 000', ContactType::Phone->rules()))->toBeTrue()
        ->and(passesRules('example.com', ContactType::Url->rules()))->toBeTrue()
        ->and(passesRules('A.Person@Example.COM ', ContactType::Email->rules()))->toBeTrue()
        ->and(passesRules('not-an-email', ContactType::Email->rules()))->toBeFalse()
        ->and(passesRules('xyz', ContactType::Phone->rules()))->toBeFalse()
        ->and(passesRules('', ContactType::Email->rules()))->toBeFalse()
        ->and(passesRules(null, ContactType::Email->rules()))->toBeFalse();
});

it('keeps a host override\'s presence rules on the field', function (): void {
    config()->set('contacts.types.email.rules', ['nullable', 'string', 'email']);

    expect(passesRules(null, ContactType::Email->rules()))->toBeTrue()
        ->and(passesRules('Someone@Example.com', ContactType::Email->rules()))->toBeTrue()
        ->and(passesRules('nope', ContactType::Email->rules()))->toBeFalse();
});

it('validates each value of a contacts array against its declared type', function (): void {
    $bad = Validator::make(['contacts' => [
        ['type' => 'email', 'value' => 'not-an-email'],
        ['type' => 'phone', 'value' => 'xyz'],
        ['type' => 'url', 'value' => 'example.com'],
    ]], Contacts::validationRules());

    expect($bad->fails())->toBeTrue()
        ->and($bad->errors()->keys())->toBe(['contacts.0.value', 'contacts.1.value']);

    $good = Validator::make(['people' => [
        ['type' => 'phone', 'value' => '+421 900 000 000'],
        ['type' => 'email', 'value' => 'A.Person@Example.COM '],
    ]], ContactRules::forArray('people'));

    expect($good->passes())->toBeTrue();
});

it('validates a registered custom kind in a contacts array with its own rules', function (): void {
    config()->set('contacts.types.whatsapp', ['rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/']]);

    $validator = Validator::make(['contacts' => [
        ['type' => 'whatsapp', 'value' => 'call me'],
        ['type' => 'whatsapp', 'value' => '+421900123456'],
    ]], ContactRules::forArray());

    expect($validator->errors()->keys())->toBe(['contacts.0.value']);
});

it('leaves an undeclared or unknown type to the type rule', function (): void {
    $validator = Validator::make(['contacts' => [
        ['type' => 'fax', 'value' => 'x'],
        ['value' => 'y'],
    ]], ContactRules::forArray());

    expect($validator->errors()->keys())->toBe(['contacts.0.type', 'contacts.1.type']);
});

it('caps a value at the column length', function (): void {
    expect(ValidContactValue::passes(ContactType::Custom, str_repeat('a', 255)))->toBeTrue()
        ->and(ValidContactValue::passes(ContactType::Custom, str_repeat('a', 256)))->toBeFalse()
        ->and(ValidContactValue::passes(ContactType::Custom, str_repeat('ž', 255)))->toBeTrue()
        ->and(passesRules('https://example.com/'.str_repeat('a', 300), ContactType::Url->rules()))->toBeFalse();
});

it('refuses an over-long value before it reaches the database', function (): void {
    $user = User::create();

    expect(fn () => $user->addUrl('https://example.com/'.str_repeat('a', 300)))
        ->toThrow(InvalidContactValue::class)
        ->and($user->contacts()->count())->toBe(0);
});

it('refuses a structured address whose render is too long', function (): void {
    $user = User::create();

    expect(fn () => $user->addStructuredAddress(AddressData::make(
        city: 'Brno', street: str_repeat('Long street ', 30), postalCode: '60200', countryIso: 'CZ',
    )))->toThrow(InvalidContactValue::class);
});

it('refuses an over-long value on update too', function (): void {
    $contact = User::create()->addUrl('example.com');

    Contacts::update($contact, new ContactData(ContactType::Url, 'https://example.com/'.str_repeat('a', 300)));
})->throws(InvalidContactValue::class);
