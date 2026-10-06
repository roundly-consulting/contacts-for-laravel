<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
     * Lock the group's live rows in key order — so concurrent promotions queue up behind one
     * another instead of deadlocking — and read each row's primary flag.
     *
     * @return Collection<int, Contact>
     */
    public static function lock(Contact $stored): Collection
    {
        return self::of($stored)
            ->orderBy($stored->getKeyName())
            ->lockForUpdate()
            ->get([$stored->getKeyName(), 'is_primary']);
    }

    /**
     * Lock the group of a row read without a lock, and return the row as it stands now — its
     * primary flag read under the lock. A write committed between that read and the lock can
     * move the row to another group (a new kind or owner) or trash it: the group just locked
     * then no longer holds it, so the row is read again under its own lock (which pins its
     * group) and the group it is in now is locked instead.
     */
    public static function lockWith(Contact $stored): Contact
    {
        $self = self::lock($stored)->first(static fn (Contact $row): bool => $row->is($stored));

        if ($self instanceof Contact) {
            return $stored->forceFill(['is_primary' => $self->is_primary])->syncOriginalAttribute('is_primary');
        }

        $current = $stored->newQueryWithoutScopes()->whereKey($stored->getKey())->lockForUpdate()->firstOrFail();

        if (! $current->trashed()) {
            self::lock($current);
        }

        return $current;
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
