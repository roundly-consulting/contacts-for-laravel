<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Models\Contact;

final class SetPrimaryContactAction
{
    public function execute(Contact $contact): Contact
    {
        if (config('contacts.require_owner_for_primary', false) && $contact->owner_id === null) {
            throw PrimaryContactConflict::requiresOwner();
        }

        return DB::transaction(function () use ($contact): Contact {
            $previous = $this->demoteSiblings($contact);

            if (! $contact->is_primary) {
                $contact->is_primary = true;
                $contact->save();
            }

            event(new PrimaryContactChanged($contact->refresh(), $previous));

            return $contact;
        });
    }

    /**
     * Demote any other primary contact of the same kind for the same owner.
     */
    private function demoteSiblings(Contact $contact): ?Contact
    {
        $query = Contact::query()
            ->where('type', $contact->type->value)
            ->where('is_primary', true)
            ->whereKeyNot($contact->getKey());

        if ($contact->owner_id === null && $contact->owner_type === null) {
            $query->whereNull('owner_id')->whereNull('owner_type');
        } else {
            $query->where('owner_id', $contact->owner_id)
                ->where('owner_type', $contact->owner_type);
        }

        $previous = $query->first();

        $query->update(['is_primary' => false]);

        return $previous;
    }
}
