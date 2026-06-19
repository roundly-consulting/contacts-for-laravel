<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Illuminate\Support\ServiceProvider;

final class ContactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/contacts.php', 'contacts');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/contacts.php' => config_path('contacts.php'),
            ], 'contacts-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'contacts-migrations');
        }
    }
}
