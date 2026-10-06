<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Contacts\Testing\ContactExpectations;
use RoundlyConsulting\Contacts\Tests\Fixtures\SwappedContactTestCase;
use RoundlyConsulting\Contacts\Tests\TestCase;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different
// base case, and a blanket bind would claim it first. `Unit` carries ArchTest.php, which
// needs the app booted — `swappableModelsAreNotFinal` reads the `contacts.model` config
// default, and an arch file is not automatically test-cased.
uses(TestCase::class)->in('Feature', 'Unit');

// The model-swap proof needs `contacts.model` pointed at the host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedContactTestCase::class)->in('ModelSwap');

ContactExpectations::register();

/**
 * Every statement the callback runs, lower-cased and unquoted, with the transaction level it
 * ran at. On SQLite a pessimistic lock compiles to nothing, so the fleet's LockRecordingGrammar
 * is installed to render it as a `/* lock-for-update *\/` marker; a real engine renders
 * `for update`.
 *
 * @return list<array{sql: string, level: int}>
 */
function contactStatements(callable $callback): array
{
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    }

    $log = [];

    DB::listen(function (QueryExecuted $query) use (&$log): void {
        $log[] = [
            'sql' => str_replace(['"', '`'], '', strtolower($query->sql)),
            'level' => $query->connection->transactionLevel(),
        ];
    });

    $callback();

    return $log;
}

/**
 * Whether a statement is a locking read of the contacts table.
 */
function locksContacts(string $sql): bool
{
    return str_starts_with($sql, 'select')
        && str_contains($sql, ' from contacts ')
        && (str_contains($sql, 'lock-for-update') || str_contains($sql, 'for update'));
}
