<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Contacts\Events\ContactDeleted;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Soft-delete a contact. Deleting the primary promotes the next contact of its kind (by
 * position) while `contacts.auto_primary` is on, so the kind keeps a primary.
 */
final readonly class DeleteContactAction
{
    public function __construct(
        private PromoteNextPrimaryContactAction $promoteNext,
    ) {}

    public function execute(Contact $contact): void
    {
        $contact->delete();

        event(new ContactDeleted($contact));

        if ($contact->is_primary) {
            $this->promoteNext->execute($contact, $contact->kind);
        }
    }
}
