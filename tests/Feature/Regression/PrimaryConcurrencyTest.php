<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Chat review C-1: two concurrent promotions in one owner + kind could both win. The demote
 * was a plain read + update with no lock, and nothing in the schema refused a second primary,
 * so on PostgreSQL's READ COMMITTED each transaction demoted without seeing the other's row.
 *
 * Promotion now locks the kind group before demoting (which serialises it on MySQL and
 * PostgreSQL alike), and a partial unique index — PostgreSQL and SQLite — refuses a second
 * live primary that the lock could not see; the promotion retries once against the winner.
 */
const ONE_PRIMARY_INDEX = 'contacts_one_primary_per_kind';

/**
 * The ids of the owner's live primaries of one kind.
 *
 * @return list<int>
 */
function livePrimaryIds(User $owner, string $kind): array
{
    return $owner->contacts()->ofType($kind)->primary()->orderBy('id')->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();
}

/**
 * A raw contact row, written past every action — what a racing transaction commits.
 *
 * @param  array<string, mixed>  $attributes
 */
function rawContact(?User $owner, string $kind, bool $primary, array $attributes = []): int
{
    return (int) DB::table('contacts')->insertGetId([
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
        'type' => $kind,
        'name' => '',
        'value' => fake()->unique()->safeEmail(),
        'is_primary' => $primary,
        'position' => 0,
        ...$attributes,
    ]);
}

/**
 * Runs `$then` once, right after the first statement demoting a promotion's siblings — the
 * point where the old code had demoted everyone and not yet promoted anyone.
 */
function afterDemotingContacts(Closure $then): void
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $then): void {
        $sql = strtolower($query->sql);

        if ($fired || ! str_starts_with($sql, 'update') || ! in_array(false, $query->bindings, true)) {
            return;
        }

        $fired = true;
        $then();
    });
}

function hasOnePrimaryIndex(): bool
{
    return in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true);
}

it('lets the database refuse a second live primary in one owner and kind', function (): void {
    $owner = User::create();
    rawContact($owner, 'email', primary: true);

    rawContact($owner, 'email', primary: true);
})->throws(UniqueConstraintViolationException::class)
    ->skip(fn (): bool => ! hasOnePrimaryIndex(), 'the one-primary index ships on PostgreSQL and SQLite only');

it('allows one live primary per kind and owner, trashed primaries and any number of plain contacts', function (): void {
    $owner = User::create();
    $other = User::create();

    rawContact($owner, 'email', primary: true);
    rawContact($owner, 'phone', primary: true);
    rawContact($other, 'email', primary: true);
    rawContact($owner, 'email', primary: false);
    rawContact($owner, 'email', primary: false);
    rawContact($owner, 'email', primary: true, attributes: ['deleted_at' => now()]);

    expect(Contact::withTrashed()->count())->toBe(6);
});

it('retries a promotion that loses the race to a concurrent primary', function (): void {
    $owner = User::create();
    $owner->addEmail('a@x.test');
    $target = $owner->addEmail('b@x.test');
    Event::fake([PrimaryContactChanged::class]);

    // The interleaving PostgreSQL allows: a racing transaction commits a primary the lock
    // could not see (inserted after the lock was taken), so the promotion collides with it.
    afterDemotingContacts(fn () => rawContact($owner, 'email', primary: true));

    Contacts::setPrimary($target);

    expect(livePrimaryIds($owner, 'email'))->toBe([$target->id]);
    Event::assertDispatchedTimes(PrimaryContactChanged::class, 1);
})->skip(fn (): bool => ! hasOnePrimaryIndex(), 'the one-primary index ships on PostgreSQL and SQLite only');

it('locks the kind group before demoting its primary', function (): void {
    $owner = User::create();
    $owner->addEmail('a@x.test');
    $target = $owner->addEmail('b@x.test');

    $log = contactStatements(fn () => Contacts::setPrimary($target));

    $lock = array_find_key($log, fn (array $entry): bool => locksContacts($entry['sql']));
    $demote = array_find_key($log, fn (array $entry): bool => str_starts_with($entry['sql'], 'update contacts set is_primary'));

    expect($lock)->not->toBeNull()
        ->and($demote)->not->toBeNull()
        ->and($lock)->toBeLessThan($demote)
        ->and($log[$lock]['level'])->toBeGreaterThanOrEqual(1)
        ->and(livePrimaryIds($owner, 'email'))->toBe([$target->id]);
});

/**
 * The index ships as a NEW, publish-only migration: hosts already ran `create_contacts_table`.
 * Rows those hosts wrote before the fix may hold two live primaries, which would make the
 * index creation fail — so the migration demotes them first, keeping the first by position.
 */
it('demotes duplicate live primaries, keeping the first by position, before indexing', function (): void {
    $connection = DB::connection();

    if (hasOnePrimaryIndex()) {
        $connection->statement('drop index '.$connection->getQueryGrammar()->wrap(ONE_PRIMARY_INDEX));
    }

    $owner = User::create();
    $other = User::create();
    $later = rawContact($owner, 'email', primary: true, attributes: ['position' => 2]);
    $kept = rawContact($owner, 'email', primary: true, attributes: ['position' => 1]);
    $tie = rawContact($owner, 'email', primary: true, attributes: ['position' => 1]);
    $trashed = rawContact($owner, 'email', primary: true, attributes: ['deleted_at' => now()]);
    $phone = rawContact($owner, 'phone', primary: true);
    $single = rawContact($other, 'email', primary: true);
    $ownerless = rawContact(null, 'email', primary: true, attributes: ['position' => 0]);
    $ownerlessDuplicate = rawContact(null, 'email', primary: true, attributes: ['position' => 4]);

    (require __DIR__.'/../../../database/migrations/update_contacts_table_with_one_primary_per_kind.php')->up();

    $primary = fn (int $id): bool => (bool) DB::table('contacts')->where('id', $id)->value('is_primary');

    expect($primary($kept))->toBeTrue()
        ->and($primary($later))->toBeFalse()
        ->and($primary($tie))->toBeFalse()
        ->and($primary($trashed))->toBeTrue()
        ->and($primary($phone))->toBeTrue()
        ->and($primary($single))->toBeTrue()
        ->and($primary($ownerless))->toBeTrue()
        ->and($primary($ownerlessDuplicate))->toBeFalse();

    if (hasOnePrimaryIndex()) {
        expect(fn () => rawContact($owner, 'email', primary: true))->toThrow(UniqueConstraintViolationException::class);
    }
});

/**
 * Runs `$then` once, right after the promotion's unlocked read of the contact — the window in
 * which a committed write can move it to another group, or trash it, before the group lock.
 */
function afterReadingContact(Contact $contact, Closure $then): void
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $then, $contact): void {
        $sql = strtolower($query->sql);

        if ($fired || ! str_starts_with($sql, 'select * from') || str_contains($sql, 'lock') || str_contains($sql, 'for update')
            || $query->bindings !== [$contact->getKey()]) {
            return;
        }

        $fired = true;
        $then();
    });
}

it('promotes a contact that moved to another kind before the lock in the kind it moved to', function (): void {
    $owner = User::create();
    $email = $owner->addEmail('a@x.test');
    $target = $owner->addEmail('b@x.test');
    $phone = $owner->addPhone('+421900000001');

    afterReadingContact($target, fn () => DB::table('contacts')->where('id', $target->id)->update(['type' => 'phone', 'value' => '+421900000002']));

    Contacts::setPrimary($target);

    expect(livePrimaryIds($owner, 'phone'))->toBe([$target->id])
        ->and($phone->fresh()?->is_primary)->toBeFalse()
        ->and(livePrimaryIds($owner, 'email'))->toBe([$email->id])
        ->and($target->kind)->toBe('phone');
});

it('refuses a contact trashed before the lock and leaves its kind alone', function (): void {
    $owner = User::create();
    $email = $owner->addEmail('a@x.test');
    $target = $owner->addEmail('b@x.test');

    afterReadingContact($target, fn () => DB::table('contacts')->where('id', $target->id)->update(['deleted_at' => now()]));

    expect(fn () => Contacts::setPrimary($target))->toThrow(PrimaryContactConflict::class, 'A deleted contact cannot be primary.')
        ->and(livePrimaryIds($owner, 'email'))->toBe([$email->id]);
});
