<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Contacts\Facades\Contacts;

final class ContactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/contacts.php', 'contacts');

        $this->app->singleton(ContactsManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'contacts');

        AliasLoader::getInstance()->alias('Contacts', Contacts::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/contacts.php' => config_path('contacts.php'),
            ], 'contacts-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'contacts-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/contacts'),
            ], 'contacts-translations');
        }
    }
}
