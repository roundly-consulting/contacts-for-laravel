<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Generate a verification token for a contact, store only its hash plus an
 * expiry, and dispatch ContactVerificationRequested with the plaintext so the
 * host application can deliver it. The package never sends anything itself.
 */
final class RequestContactVerificationAction
{
    public function execute(Contact $contact): string
    {
        $plain = $this->generateToken();

        $ttl = (int) config('contacts.verification.ttl', 60);

        $contact->verification_token = Hash::make($plain);
        $contact->verification_expires_at = Carbon::now()->addMinutes(max(1, $ttl));
        $contact->save();

        event(new ContactVerificationRequested($contact, $plain));

        return $plain;
    }

    private function generateToken(): string
    {
        $style = config('contacts.verification.style', 'code');

        if ($style === 'token') {
            $bytes = (int) config('contacts.verification.token_length', 32);

            return bin2hex(random_bytes(max(1, $bytes)));
        }

        $length = (int) config('contacts.verification.code_length', 6);
        $length = max(1, $length);

        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
