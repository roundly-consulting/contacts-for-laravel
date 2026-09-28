<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Carbon\CarbonInterface;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Exceptions\VerificationExpired;
use RoundlyConsulting\Contacts\Models\Contact;
use SensitiveParameter;

/**
 * `Contacts::verification()` — the token / code flow. Only a hash of the token is
 * stored; delivery is the host's job (listen for ContactVerificationRequested). Every
 * call goes through the manager, so host overrides and `Contacts::fake()` see it.
 */
final readonly class ContactVerification
{
    /**
     * @internal build it with `Contacts::verification()`
     */
    public function __construct(
        private ContactsManager $contacts,
    ) {}

    /**
     * Issue a verification token, store its hash and expiry, and dispatch
     * ContactVerificationRequested. Returns the plaintext token for delivery.
     */
    public function request(Contact $contact): string
    {
        return $this->contacts->requestVerification($contact);
    }

    /**
     * Confirm a contact against the plaintext token it was sent.
     *
     * @throws InvalidVerificationToken when the token is wrong or none was issued
     * @throws VerificationExpired when the token is past its TTL
     */
    public function confirm(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        return $this->contacts->confirmVerification($contact, $token);
    }

    /**
     * Mark a contact verified without a token (e.g. verified out of band).
     */
    public function markVerified(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        return $this->contacts->markVerified($contact, $at);
    }
}
