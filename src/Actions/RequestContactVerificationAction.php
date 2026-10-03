<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactsConfig;
use RoundlyConsulting\Crypto\Codec\Hex;
use RoundlyConsulting\Crypto\Random\Bytes;
use RoundlyConsulting\Crypto\Random\Token;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Generate a verification token for a contact, store only its hash plus an
 * expiry, and dispatch ContactVerificationRequested with the plaintext so the
 * host application can deliver it. The package never sends anything itself.
 */
final readonly class RequestContactVerificationAction
{
    /**
     * Bytes of input bcrypt actually hashes; anything after is ignored.
     */
    private const int BCRYPT_INPUT_LIMIT = 72;

    public function execute(Contact $contact): string
    {
        $plain = $this->generateToken();

        $ttl = ContactsConfig::verificationTtl();

        $contact->verification_token = Hash::make($plain);
        $contact->verification_expires_at = Carbon::now()->addMinutes($ttl);
        // A fresh token gets a fresh wrong-guess budget; the old token is gone with it.
        $contact->verification_attempts = 0;
        $contact->save();

        event(new ContactVerificationRequested($contact, $plain));

        return $plain;
    }

    /**
     * The CSPRNG draw and the hex encoding live in crypto-for-laravel; this only owns the
     * choice of style and length.
     *
     * Both lengths must fit the hash: bcrypt reads only the first 72 bytes of its input, so
     * a longer token would be checked only in part (36 bytes hex-encode to 72 characters).
     * A length outside its range fails loudly rather than being silently clamped, and so does
     * a style that is neither `code` nor `token`: a typo never downgrades a token to a code.
     *
     * `Token::numeric()` draws each digit uniformly rather than one integer in
     * [0, 10^n - 1]. That is the same distribution over the same n-digit space — including
     * the leading-zero codes the old `str_pad` preserved — and it removes an integer
     * overflow: `10 ** $length` exceeds PHP_INT_MAX at length 19, becoming a float that
     * `random_int()` rejects with a TypeError.
     */
    private function generateToken(): string
    {
        if (ContactsConfig::verificationStyle() === ContactsConfig::STYLE_TOKEN) {
            $bytes = Config::integer('contacts.verification.token_length', 32, min: 1, max: intdiv(self::BCRYPT_INPUT_LIMIT, 2));

            return Hex::encode(Bytes::generate($bytes));
        }

        return Token::numeric(Config::integer('contacts.verification.code_length', 6, min: 1, max: self::BCRYPT_INPUT_LIMIT));
    }
}
