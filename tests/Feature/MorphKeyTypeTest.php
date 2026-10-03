<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The `contacts.key_type` seam. A contact optionally belongs to a polymorphic owner; that
 * `owner_id` must follow the host's key type or a uuid/ulid-keyed owner cannot hold a
 * contact on a strict engine. The shipped bigint schema stays byte-identical.
 */
if (! function_exists('morphKtColumn')) {
    /**
     * @return array{type: string, type_name: string, nullable: bool|null}
     */
    function morphKtColumn(string $table, string $column): array
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $column) {
                return ['type' => $c['type'], 'type_name' => $c['type_name'], 'nullable' => $c['nullable']];
            }
        }

        return ['type' => 'MISSING', 'type_name' => 'MISSING', 'nullable' => null];
    }
}

$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';
$table = fn (): string => (string) config('contacts.table', 'contacts');

it('emits byte-identical morph columns on the bigint default', function () use ($table): void {
    // The owner is an optional (nullable) morph.
    Schema::dropIfExists('kt_ref');
    Schema::create('kt_ref', function (Blueprint $t): void {
        $t->id();
        $t->nullableMorphs('opt');
    });

    expect(morphKtColumn($table(), 'owner_id'))->toBe(morphKtColumn('kt_ref', 'opt_id'))
        ->and(morphKtColumn($table(), 'owner_type'))->toBe(morphKtColumn('kt_ref', 'opt_type'));

    Schema::dropIfExists('kt_ref');
});

it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected) use ($table): void {
    config()->set('contacts.key_type', $keyType);

    Schema::dropIfExists($table());
    $migration = require __DIR__.'/../../database/migrations/create_contacts_table.php';
    $migration->up();

    expect(morphKtColumn($table(), 'owner_id')['type'])->toBe($expected)
        ->and(morphKtColumn($table(), 'owner_type')['type'])->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart — sqlite affinity hides it');

it('refuses to migrate on an unrecognized key type instead of falling back to bigint', function () use ($table): void {
    config()->set('contacts.key_type', 'nonsense');

    Schema::dropIfExists($table());

    // A typo in a host's config must stop the migration, never silently build bigint
    // columns for a uuid/ulid-keyed host.
    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/create_contacts_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [contacts.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});
