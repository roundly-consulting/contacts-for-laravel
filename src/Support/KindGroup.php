<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * The contacts that share one primary: the same owner (or all owner-less) and the same
 * RAW kind. The raw kind, not the enum: every registered custom kind types as Custom, so
 * grouping on the enum would let a `whatsapp` primary compete with a `telegram` one.
 *
 * Live rows only — a trashed contact holds no primary slot.
 *
 * @internal
 */
final class KindGroup
{
    /**
     * @return Builder<Contact>
     */
    public static function of(Contact $contact, ?string $kind = null): Builder
    {
        $query = $contact->newQuery()->where('type', $kind ?? $contact->kind);

        if ($contact->owner_id === null && $contact->owner_type === null) {
            return $query->whereNull('owner_id')->whereNull('owner_type');
        }

        return $query->where('owner_id', $contact->owner_id)
            ->where('owner_type', $contact->owner_type);
    }

    /**
     * Whether another contact of the group already holds the primary.
     */
    public static function hasOtherPrimary(Contact $contact): bool
    {
        return self::of($contact)
            ->whereKeyNot($contact->getKey())
            ->where('is_primary', true)
            ->exists();
    }
}
