<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\KindGroup;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Make a contact the one primary of its owner + kind group, demoting the rest — in one
 * transaction, under a lock on the group, so two concurrent promotions cannot both win. The
 * stored row is the source of truth: its group and trash state are read under the lock, never
 * taken from the caller's in-memory copy.
 *
 * The lock serialises promotions on MySQL and PostgreSQL alike. PostgreSQL's lock cannot see a
 * primary another transaction writes after it was taken; the partial unique index the package
 * ships (PostgreSQL and SQLite) refuses that second primary, and the promotion retries once
 * against the committed winner. MySQL has no partial index, so a primary written past the
 * actions there relies on the lock alone; owner-less contacts are not covered by the index
 * (NULL owners never collide in a unique index) and rely on the lock too.
 */
final readonly class SetPrimaryContactAction
{
    /**
     * @throws PrimaryContactConflict when the contact is deleted, or has no owner while
     *                                `contacts.require_owner_for_primary` is on
     * @throws ModelNotFoundException<Contact> when the contact is gone
     */
    public function execute(Contact $contact): Contact
    {
        if ($contact->trashed()) {
            throw PrimaryContactConflict::trashed();
        }

        if ($contact->owner_id === null && Config::boolean('contacts.require_owner_for_primary')) {
            throw PrimaryContactConflict::requiresOwner();
        }

        try {
            $previous = $this->attempt($contact);
        } catch (UniqueConstraintViolationException) {
            // A racing promotion committed a primary the lock could not see (on PostgreSQL: a
            // row written after the lock was taken), and the one-primary index refused ours.
            // The winner is visible now, so a second pass demotes it.
            $previous = $this->attempt($contact);
        }

        event(new PrimaryContactChanged($contact->refresh(), $previous));

        return $contact;
    }

    /**
     * @return Contact|null the primary the contact took over from
     */
    private function attempt(Contact $contact): ?Contact
    {
        return $contact->getConnection()->transaction(function () use ($contact): ?Contact {
            $stored = KindGroup::lockWith(
                $contact->newQueryWithoutScopes()->whereKey($contact->getKey())->firstOrFail(),
            );

            if ($stored->trashed()) {
                throw PrimaryContactConflict::trashed();
            }

            $siblings = KindGroup::of($stored)
                ->whereKeyNot($stored->getKey())
                ->where('is_primary', true);

            $previous = (clone $siblings)->first();

            $siblings->update(['is_primary' => false]);

            if (! $stored->is_primary) {
                $stored->newQueryWithoutScopes()->whereKey($stored->getKey())->update(['is_primary' => true]);
            }

            return $previous;
        }, 3);
    }
}
