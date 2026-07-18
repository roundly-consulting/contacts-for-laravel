<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\RequestContactVerificationAction;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Freezes the OUTPUT SHAPE and DISTRIBUTION of verification-token generation so routing it
 * through crypto-for-laravel's `Random\Bytes` / `Random\Token` is provably a no-op.
 *
 * This token is what proves ownership of an email address or a phone number. A refactor
 * that silently changed its length, alphabet or zero-padding would be a real regression —
 * a shorter code is easier to brute force, and a lost `str_pad` would quietly drop every
 * leading-zero code from the space, costing ~10% of it. The existing action tests pin one
 * length and one charset; nothing pins the padding or the draw.
 *
 * The distribution is compared against a REFERENCE COPY of the pre-refactor generator
 * (below) rather than merely asserted to "look random". "It compiles" is not evidence about
 * a CSPRNG's output.
 */

/**
 * The pre-refactor `RequestContactVerificationAction::generateToken()` numeric branch,
 * verbatim. Duplicated on purpose: a reference that delegates to the code under test proves
 * nothing.
 */
function referenceNumericCode(int $length): string
{
    $length = max(1, $length);
    $max = (10 ** $length) - 1;

    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

/**
 * The pre-refactor token branch, verbatim.
 */
function referenceHexToken(int $bytes): string
{
    return bin2hex(random_bytes(max(1, $bytes)));
}

/**
 * Two-sample chi-square homogeneity statistic over two symbol-frequency maps.
 *
 * @param  array<string, int>  $a
 * @param  array<string, int>  $b
 */
function chiSquareTwoSampleContacts(array $a, array $b): float
{
    $totalA = array_sum($a);
    $totalB = array_sum($b);
    $chi = 0.0;

    foreach (array_keys($a + $b) as $key) {
        $countA = $a[$key] ?? 0;
        $countB = $b[$key] ?? 0;
        $combined = $countA + $countB;

        if ($combined === 0) {
            continue;
        }

        $expectedA = $combined * $totalA / ($totalA + $totalB);
        $expectedB = $combined * $totalB / ($totalA + $totalB);

        $chi += (($countA - $expectedA) ** 2) / $expectedA;
        $chi += (($countB - $expectedB) ** 2) / $expectedB;
    }

    return $chi;
}

/**
 * @return array<string, int>
 */
function symbolFrequency(string $sample): array
{
    $mapped = [];

    foreach (count_chars($sample, 1) as $ordinal => $count) {
        // strval: PHP coerces numeric-string keys to int, which would break comparison.
        $mapped[(string) chr((int) $ordinal)] = $count;
    }

    return $mapped;
}

beforeEach(function (): void {
    // The action bcrypt-hashes every token it mints. These tests draw thousands, so the
    // cost factor is dropped to the minimum — this measures the GENERATOR, not the hasher.
    config()->set('hashing.bcrypt.rounds', 4);
});

/**
 * Draw $count plaintext tokens through the real action.
 *
 * @return list<string>
 */
function drawTokens(int $count): array
{
    $contact = Contact::factory()->email()->create();
    $action = app(RequestContactVerificationAction::class);

    $tokens = [];

    for ($i = 0; $i < $count; $i++) {
        $tokens[] = $action->execute($contact);
    }

    return $tokens;
}

it('freezes the numeric code shape: exactly code_length digits, for every configured length', function (): void {
    config()->set('contacts.verification.style', 'code');

    // A sweep, not a single length: the old draw and the new one must agree everywhere,
    // not just at the default 6.
    foreach ([1, 2, 4, 6, 8, 12, 18] as $length) {
        config()->set('contacts.verification.code_length', $length);

        $token = drawTokens(1)[0];

        expect(strlen($token))->toBe($length)
            ->and($token)->toMatch('/^[0-9]+$/');
    }
});

it('freezes the zero-padding: short draws stay padded to full width', function (): void {
    config()->set('contacts.verification.style', 'code');
    config()->set('contacts.verification.code_length', 6);

    $tokens = drawTokens(600);

    // Every code is full width — this is what str_pad bought, and what a naive
    // `(string) random_int(...)` would silently lose.
    foreach ($tokens as $token) {
        expect(strlen($token))->toBe(6);
    }

    // And leading-zero codes are really in the space: P(none of 600) = 0.9^600 ~ 1e-27.
    $leadingZero = array_filter($tokens, static fn (string $t): bool => str_starts_with($t, '0'));

    expect($leadingZero)->not->toBeEmpty(
        'No code of 600 started with 0 — the generator has dropped ~10% of its space.',
    );
});

it('freezes the numeric distribution against the pre-refactor generator', function (): void {
    config()->set('contacts.verification.style', 'code');
    config()->set('contacts.verification.code_length', 6);

    $new = implode('', drawTokens(600));

    $reference = '';
    for ($i = 0; $i < 600; $i++) {
        $reference .= referenceNumericCode(6);
    }

    $chi = chiSquareTwoSampleContacts(symbolFrequency($new), symbolFrequency($reference));

    // df = 9. Threshold 40 sits ~7 sigma above the mean (9, sd 4.2): a true no-op
    // effectively never trips it, while a skewed or narrowed draw lands far beyond.
    expect($chi)->toBeLessThan(40.0);
});

it('freezes the hex token shape: exactly twice token_length lowercase hex chars', function (): void {
    config()->set('contacts.verification.style', 'token');

    foreach ([1, 4, 16, 32, 64] as $bytes) {
        config()->set('contacts.verification.token_length', $bytes);

        $token = drawTokens(1)[0];

        // Hex-encoded, so the string is twice the byte count — as config/contacts.php promises.
        expect(strlen($token))->toBe($bytes * 2)
            ->and($token)->toMatch('/^[0-9a-f]+$/');
    }
});

it('freezes the hex distribution against the pre-refactor generator', function (): void {
    config()->set('contacts.verification.style', 'token');
    config()->set('contacts.verification.token_length', 16);

    $new = implode('', drawTokens(200));

    $reference = '';
    for ($i = 0; $i < 200; $i++) {
        $reference .= referenceHexToken(16);
    }

    $chi = chiSquareTwoSampleContacts(symbolFrequency($new), symbolFrequency($reference));

    // df = 15. Threshold 60 is ~9 sigma above the mean (15, sd 5.5).
    expect($chi)->toBeLessThan(60.0);
});

/**
 * Regression: `code_length` of 19 or more used to be a fatal.
 *
 * The old generator computed `(10 ** $length) - 1` as `random_int()`'s upper bound. At
 * length 19 that exceeds PHP_INT_MAX (9223372036854775807), so `10 ** 19` evaluates to the
 * float 1.0E+19 and `random_int()` rejects it with a TypeError — an uncaught fatal on a
 * host-supplied, env-backed config value (CONTACTS_VERIFICATION_CODE_LENGTH). Nothing
 * validated the ceiling and no test swept past 6.
 *
 * Drawing each digit uniformly has no such bound, so the length is now simply honoured.
 */
it('mints a long numeric code instead of fatally overflowing its own bound', function (int $length): void {
    config()->set('contacts.verification.style', 'code');
    config()->set('contacts.verification.code_length', $length);

    $token = drawTokens(1)[0];

    expect(strlen($token))->toBe($length)
        ->and($token)->toMatch('/^[0-9]+$/');
})->with([19, 25, 40]);

it('freezes the hex alphabet: lowercase only, all 16 symbols reachable', function (): void {
    config()->set('contacts.verification.style', 'token');
    config()->set('contacts.verification.token_length', 32);

    $sample = implode('', drawTokens(100));

    $seen = array_map(strval(...), array_keys(symbolFrequency($sample)));
    sort($seen);

    $expected = str_split('0123456789abcdef');
    sort($expected);

    expect($seen)->toBe($expected);
});
