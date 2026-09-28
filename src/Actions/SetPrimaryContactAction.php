<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\KindGroup;
use RoundlyConsulting\PackageToolkit\Support\Config;

final readonly class SetPrimaryContactAction
{
    public function execute(Contact $contact): Contact
    {
        if ($contact->owner_id === null && Config::boolean('contacts.require_owner_for_primary')) {
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
        $query = KindGroup::of($contact)
            ->where('is_primary', true)
            ->whereKeyNot($contact->getKey());

        $previous = $query->first();

        $query->update(['is_primary' => false]);

        return $previous;
    }
}
