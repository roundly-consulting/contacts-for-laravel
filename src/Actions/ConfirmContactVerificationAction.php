<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Events\ContactVerified;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Exceptions\VerificationExpired;
use RoundlyConsulting\Contacts\Models\Contact;
use SensitiveParameter;

/**
 * Confirm a contact against a plaintext verification token. On success the
 * contact is marked verified, the token fields are cleared, and the existing
 * ContactVerified event fires.
 */
final class ConfirmContactVerificationAction
{
    public function execute(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        if ($contact->verified_at !== null) {
            return $contact;
        }

        if ($contact->verification_token === null) {
            throw InvalidVerificationToken::make();
        }

        if (! Hash::check($token, $contact->verification_token)) {
            throw InvalidVerificationToken::make();
        }

        if ($contact->verification_expires_at !== null
            && $contact->verification_expires_at->isPast()) {
            throw VerificationExpired::make();
        }

        $contact->verified_at = Carbon::now();
        $contact->verification_token = null;
        $contact->verification_expires_at = null;
        $contact->save();

        event(new ContactVerified($contact));

        return $contact;
    }
}
