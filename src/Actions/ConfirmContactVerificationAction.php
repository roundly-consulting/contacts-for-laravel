<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Events\ContactVerified;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Exceptions\VerificationAttemptsExceeded;
use RoundlyConsulting\Contacts\Exceptions\VerificationExpired;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\PackageToolkit\Support\Config;
use SensitiveParameter;

/**
 * Confirm a contact against a plaintext verification token. On success the
 * contact is marked verified, the token fields are cleared, and the existing
 * ContactVerified event fires.
 *
 * Every step that matters is a conditional UPDATE on the stored token hash rather than
 * a read-then-write on the in-memory model, so the checks hold across parallel requests
 * and stale copies of the contact:
 *
 * - an attempt is **spent before** the hash is compared, and only while the budget
 *   (`contacts.verification.max_attempts`) lasts — parallel guesses can never evaluate
 *   more than the budget allows;
 * - the guess that spends the last attempt voids the token;
 * - success only lands while that same token is still the stored one. A value change
 *   voids the token in the same write, so a token issued for an old value can never mark
 *   a new value verified.
 */
final readonly class ConfirmContactVerificationAction
{
    public function execute(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        if ($contact->verified_at !== null) {
            return $contact;
        }

        $hash = $contact->verification_token;

        if ($hash === null) {
            throw InvalidVerificationToken::make();
        }

        // Before an attempt is spent: the answer does not depend on the token, so an
        // expired one costs nothing and reveals nothing.
        if ($contact->verification_expires_at !== null
            && $contact->verification_expires_at->isPast()) {
            throw VerificationExpired::make();
        }

        $maxAttempts = Config::integer('contacts.verification.max_attempts', 5, min: 1, max: 1000);

        $reserved = $this->current($contact, $hash)
            ->where('verification_attempts', '<', $maxAttempts)
            ->increment('verification_attempts');

        if ($reserved === 0) {
            // Either the token was replaced or voided since this copy was loaded, or its
            // budget is already spent (e.g. max_attempts was lowered).
            if ($this->current($contact, $hash)->exists()) {
                $this->void($contact, $hash);

                throw VerificationAttemptsExceeded::make();
            }

            throw InvalidVerificationToken::make();
        }

        if (! Hash::check($token, $hash)) {
            $attempts = (int) $this->row($contact)->value('verification_attempts');

            $contact->forceFill(['verification_attempts' => $attempts])
                ->syncOriginalAttribute('verification_attempts');

            if ($attempts >= $maxAttempts) {
                $this->void($contact, $hash);

                throw VerificationAttemptsExceeded::make();
            }

            throw InvalidVerificationToken::make();
        }

        $verified = [
            'verified_at' => Carbon::now(),
            'verification_token' => null,
            'verification_expires_at' => null,
            'verification_attempts' => 0,
        ];

        if ($this->current($contact, $hash)->update($verified) === 0) {
            throw InvalidVerificationToken::make();
        }

        $contact->forceFill($verified)->syncOriginalAttributes(array_keys($verified));

        event(new ContactVerified($contact));

        return $contact;
    }

    /**
     * @return Builder<Contact>
     */
    private function row(Contact $contact): Builder
    {
        return $contact->newQuery()->whereKey($contact->getKey());
    }

    /**
     * The contact's row, only while `$hash` is still its stored token.
     *
     * @return Builder<Contact>
     */
    private function current(Contact $contact, string $hash): Builder
    {
        return $this->row($contact)->where('verification_token', $hash);
    }

    private function void(Contact $contact, string $hash): void
    {
        $void = ['verification_token' => null, 'verification_expires_at' => null];

        $this->current($contact, $hash)->update($void);

        $contact->forceFill($void)->syncOriginalAttributes(array_keys($void));
    }
}
