<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5,
 * on three packages). `count: 1` pins the file count so neither check can pass over an
 * empty or relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(ContactsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(ContactsServiceProvider::class)->toPublishMigrationsTimestamped('contacts-migrations', 1);
});

/**
 * M — `toHaveRunnableMigrationOrder` — is deliberately NOT adopted, and this note is the
 * cause rather than an omission.
 *
 * `MigrationGraph::assertRunnable()` checks two independent things and only one is about
 * foreign keys: it also pins that a `Schema::table()` ALTER sorts at or after the CREATE
 * of the table it alters (approvals #2). Contacts ships **one CREATE, zero FK edges and
 * zero ALTERs** — verified against the migration source, not the row spec — so *both*
 * halves are inert. There is no edge to order and no ALTER to place.
 *
 * The contrast with the packages either side of it in this wave is the clean
 * illustration: `approvals` also has 0 FKs but ships 2 ALTERs, so it adopts M with a
 * `foreignKeys: 0` live pin; `connections` has 0 FKs and 0 ALTERs and rejects it, as this
 * row does. Same FK count, opposite outcomes — the criterion is FK edges OR ALTERs.
 *
 * Every owner column here is a `nullableMorphs('owner')`, deliberately unconstrained
 * because a host's contact owner can live in any table. If a real FK or an ALTER is ever
 * added, this row must adopt M rather than inherit this note.
 */

/**
 * R — the real-engine proof. Contacts' DDL had never met a real engine before this row,
 * and it ships a `jsonb` column, which is precisely what SQLite cannot judge:
 * `SQLiteGrammar::typeJsonb()` renders it as plain `text` unless `use_native_jsonb` is
 * on, so the sqlite leg would call a broken jsonb column green forever.
 *
 * `migrations: 1` pins the count, and the expectation additionally fails a set that
 * "applies cleanly" while creating no tables — an empty `up()` otherwise passes and
 * proves nothing.
 *
 * The negative control (`toRejectBrokenOrderOnConnection`) is deliberately NOT adopted:
 * it asserts the engine *refuses* a reordered set, and with a single migration the
 * reversed list is the same list — and with zero foreign keys Postgres has nothing to
 * refuse regardless, so it would fail loudly by design. That is the assertion working
 * correctly against a shape it does not fit, not a red to chase.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the driver the leg *declares* (TESTING_DB_DRIVER)
 * against what the connection itself *answers*, so a "pgsql" job that quietly ran on
 * SQLite — the exact failure the whole leg exists to prevent — is impossible rather than
 * merely detectable by reading a skip count. It caught the 3a decapitation.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The `jsonb` meta column and the morph columns are what the drivers render differently.
 * Pinning a round-trip on whatever engine the leg configured proves the columns are
 * usable rather than merely creatable — and `meta` is the one that matters: as `json`,
 * Postgres has no equality operator at all, so a `where` against it is not a weaker
 * assertion but an impossible one.
 */
it('round-trips a contact with json meta on the configured engine', function (): void {
    $user = User::create(['name' => 'Ada']);

    $contact = $user->addEmail('ada@example.com', 'work');
    $contact->meta = ['region' => 'eu', 'tier' => 2, 'verified_channel' => 'inbox'];
    $contact->save();

    $fresh = $contact->fresh();

    // Key-by-key `toBe`, not `toEqual` on the whole map: jsonb sorts object keys, so a
    // whole-map `toBe` would be asserting Postgres' key order rather than the values —
    // and `toEqual` is `==`, which would let the int 2 pass as the string "2" and so
    // stop proving the driver renders the value faithfully.
    expect($fresh->meta['region'])->toBe('eu')
        ->and($fresh->meta['tier'])->toBe(2)
        ->and($fresh->meta['verified_channel'])->toBe('inbox')
        ->and($fresh->type)->toBe(ContactType::Email)
        ->and($fresh->owner_type)->toBe($user->getMorphClass())
        ->and($fresh->is_primary)->toBeTrue();
});
