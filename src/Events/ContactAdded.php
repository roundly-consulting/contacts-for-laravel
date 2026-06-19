<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Events;

use RoundlyConsulting\Contacts\Models\Contact;

final class ContactAdded
{
    public function __construct(
        public readonly Contact $contact,
    ) {}
}
