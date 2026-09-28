<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Crypto\Codec\Hex;
use RoundlyConsulting\Crypto\Random\Bytes;
use RoundlyConsulting\Crypto\Random\Token;

/**
 * Generate a verification token for a contact, store only its hash plus an
 * expiry, and dispatch ContactVerificationRequested with the plaintext so the
 * host application can deliver it. The package never sends anything itself.
 */
final readonly class RequestContactVerificationAction
{
    public function execute(Contact $contact): string
    {
        $plain = $this->generateToken();

        $ttl = (int) config('contacts.verification.ttl', 60);

        $contact->verification_token = Hash::make($plain);
        $contact->verification_expires_at = Carbon::now()->addMinutes(max(1, $ttl));
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
     * `Token::numeric()` draws each digit uniformly rather than one integer in
     * [0, 10^n - 1]. That is the same distribution over the same n-digit space — including
     * the leading-zero codes the old `str_pad` preserved — and it removes an integer
     * overflow: `10 ** $length` exceeds PHP_INT_MAX at length 19, becoming a float that
     * `random_int()` rejects with a TypeError.
     */
    private function generateToken(): string
    {
        $style = config('contacts.verification.style', 'code');

        if ($style === 'token') {
            $bytes = (int) config('contacts.verification.token_length', 32);

            return Hex::encode(Bytes::generate(max(1, $bytes)));
        }

        $length = (int) config('contacts.verification.code_length', 6);

        return Token::numeric(max(1, $length));
    }
}
