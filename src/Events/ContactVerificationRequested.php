<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Events;

use RoundlyConsulting\Contacts\Models\Contact;
use SensitiveParameter;

/**
 * Fired when a verification token is generated for a contact. The host app
 * listens for this and delivers the plaintext token to the owner over its own
 * channel (mail, SMS, …). The package never sends anything itself.
 */
final class ContactVerificationRequested
{
    public function __construct(
        public readonly Contact $contact,
        #[SensitiveParameter]
        public readonly string $plainToken,
    ) {}
}
