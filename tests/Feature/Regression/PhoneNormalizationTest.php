<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Review finding: with `default_country_code` set, national formats came out wrong — the
 * trunk `0` was kept ('0900 123 456' → '+4210900123456', not a valid E.164 number) and the
 * `00` international prefix was treated as part of the number ('00421 …' rejected).
 */
it('normalizes national and international-prefix formats', function (string|int|null $country, string $input, string $expected): void {
    config()->set('contacts.default_country_code', $country);

    expect(ContactType::Phone->normalize($input))->toBe($expected);
})->with([
    'trunk 0 dropped' => ['421', '0900 123 456', '+421900123456'],
    'trunk 0, country with plus' => ['+421', '0900 123 456', '+421900123456'],
    '00 prefix' => ['421', '00421 900 123 456', '+421900123456'],
    '00 prefix, no default country' => [null, '00421 900 123 456', '+421900123456'],
    '00 prefix, foreign country' => ['421', '0044 20 7946 0000', '+442079460000'],
    'plain national' => ['421', '900 123 456', '+421900123456'],
    'already international' => ['421', '+421 900 123 456', '+421900123456'],
    'UK (0) trunk marker' => [null, '+44 (0)20 7946 0000', '+442079460000'],
    'Italy keeps its leading 0' => ['39', '06 1234 5678', '+390612345678'],
    'integer country code' => [421, '0900 123 456', '+421900123456'],
    'no country: national left as digits' => [null, '0900 123 456', '0900123456'],
]);

it('accepts the national formats through the trait', function (): void {
    config()->set('contacts.default_country_code', '421');
    $user = User::create();

    expect($user->addPhone('0900 123 456')->value)->toBe('+421900123456')
        ->and($user->addPhone('00421 900 123 457')->value)->toBe('+421900123457');
});
