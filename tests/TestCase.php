<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AddressesServiceProvider::class,
            ConnectionsServiceProvider::class,
            ContactsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Migrations are publish-only — no provider auto-loads them, so the suite
        // runs every table it needs itself, in dependency order.
        $migration = include __DIR__.'/../database/migrations/create_contacts_table.php';
        $migration->up();

        // The addresses and connections integrations own their own morph tables;
        // load them from the provider packages so the cross-package tests run.
        $addresses = include dirname(__DIR__).'/vendor/roundly-consulting/addresses-for-laravel/database/migrations/create_addresses_table.php';
        $addresses->up();

        $connections = include dirname(__DIR__).'/vendor/roundly-consulting/connections-for-laravel/database/migrations/create_connections_table.php';
        $connections->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
    }
}
