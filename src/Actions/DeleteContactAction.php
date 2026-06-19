<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Contacts\Events\ContactDeleted;
use RoundlyConsulting\Contacts\Models\Contact;

final class DeleteContactAction
{
    public function execute(Contact $contact): void
    {
        $contact->delete();

        event(new ContactDeleted($contact));
    }
}
