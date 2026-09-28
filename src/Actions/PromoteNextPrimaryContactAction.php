<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\KindGroup;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Keep `contacts.auto_primary`'s promise after the primary leaves a kind (deleted, synced
 * away, or moved to another kind): when the kind still has contacts but no primary, the
 * first by position is promoted. With `auto_primary` off nothing is promoted, and an
 * owner-less group is left alone while `require_owner_for_primary` is on.
 *
 * @internal building block of delete, sync and update
 */
final readonly class PromoteNextPrimaryContactAction
{
    public function __construct(
        private SetPrimaryContactAction $setPrimary,
    ) {}

    /**
     * @param  Contact  $member  any contact of the owner the group belongs to
     */
    public function execute(Contact $member, string $kind): ?Contact
    {
        if (! Config::boolean('contacts.auto_primary', true)) {
            return null;
        }

        if ($member->owner_id === null && Config::boolean('contacts.require_owner_for_primary')) {
            return null;
        }

        $group = KindGroup::of($member, $kind);

        if ((clone $group)->where('is_primary', true)->exists()) {
            return null;
        }

        $next = $group->ordered()->first();

        return $next instanceof Contact ? $this->setPrimary->execute($next) : null;
    }
}
