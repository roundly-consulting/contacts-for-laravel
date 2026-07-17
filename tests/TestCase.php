<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider contacts needs, in registration order. Addresses and connections
     * are hard `require`s a host would auto-discover, and the suite genuinely exercises
     * both (the address integration and the typed relationship helpers), so listing
     * them is what makes the test env match a real install rather than a fiction.
     *
     * `enums-for-laravel` and `package-toolkit-for-laravel` are hard `require`s too but
     * the first ships no provider (helpers only) and the second is a base class rather
     * than a registered package, so the list is genuinely three entries.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            AddressesServiceProvider::class,
            ConnectionsServiceProvider::class,
            ContactsServiceProvider::class,
        ];
    }

    /**
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `include`, which is what the old base case did:
     * it reached into `vendor/roundly-consulting/<pkg>/database/migrations/<literal
     * filename>.php` and called `->up()` on the returned object. That coupled this
     * suite to two *other* packages' migration filenames through a path string no
     * refactor would ever update — a rename in addresses or connections broke contacts
     * with a file-not-found, not a red test.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            AddressesServiceProvider::class,
            ConnectionsServiceProvider::class,
            ContactsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
