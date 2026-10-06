<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Contacts\Support\ContactsConfig;

/**
 * At most one live primary per owner + kind, enforced by the engine.
 *
 * Promotion locks the owner's kind group, which serialises it on MySQL and PostgreSQL. But
 * PostgreSQL's `FOR UPDATE` cannot see a primary committed after the lock was taken, so two
 * promotions racing that way would both win: this partial unique index refuses the second,
 * and the promotion retries against the committed winner. SQLite gets the same index
 * (identical syntax), so the default test engine exercises the guard. MySQL has no partial
 * indexes and relies on the lock. Trashed rows hold no slot (a deleted primary keeps its flag
 * so a restore can undo the delete), and owner-less contacts are not covered — NULL owners
 * never collide in a unique index.
 *
 * Shipped as its own migration, after `create_contacts_table`, because hosts already ran that
 * one. Rows written before the index may hold two live primaries in a group, which would make
 * creating it fail, so those are demoted first: the first by position (then id) keeps the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Not set (absent, null or blank) means the conventional name; anything else must be a string.
        $table = ContactsConfig::table() ?? 'contacts';

        $connection = Schema::getConnection();

        $this->demoteDuplicatePrimaries($connection, $table);

        if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'create unique index %s on %s (%s) where %s and %s is null',
            $grammar->wrap($connection->getTablePrefix().$table.'_one_primary_per_kind'),
            $grammar->wrapTable($table),
            $grammar->columnize(['owner_type', 'owner_id', 'type']),
            $grammar->wrap('is_primary'),
            $grammar->wrap('deleted_at'),
        ));
    }

    /**
     * Keep one live primary per owner + kind — the first by position, then id, the contact
     * auto-promotion would pick — and demote the rest.
     */
    private function demoteDuplicatePrimaries(Connection $connection, string $table): void
    {
        $groups = $this->livePrimaries($connection, $table)
            ->select(['owner_type', 'owner_id', 'type'])
            ->groupBy(['owner_type', 'owner_id', 'type'])
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = $this->livePrimaries($connection, $table)
                ->where('type', $group->type)
                ->where(fn (Builder $query): Builder => $group->owner_type === null
                    ? $query->whereNull('owner_type')
                    : $query->where('owner_type', $group->owner_type))
                ->where(fn (Builder $query): Builder => $group->owner_id === null
                    ? $query->whereNull('owner_id')
                    : $query->where('owner_id', $group->owner_id))
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id');

            $connection->table($table)
                ->whereIn('id', $ids->slice(1)->values()->all())
                ->update(['is_primary' => false]);
        }
    }

    private function livePrimaries(Connection $connection, string $table): Builder
    {
        return $connection->table($table)
            ->where('is_primary', true)
            ->whereNull('deleted_at');
    }
};
