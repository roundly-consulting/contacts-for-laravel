<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class ContactsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('contacts')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasFacadeAlias(Contacts::class)
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(ContactModel::class()),
                'Table' => (string) config('contacts.table', 'contacts'),
                'Auto primary' => config('contacts.auto_primary', true) === true ? 'ON' : 'OFF',
                'Require owner for primary' => config('contacts.require_owner_for_primary') === true ? 'ON' : 'OFF',
                'Default country code' => self::countryCode(),
                'Verification' => self::verification(),
                // Counts, never the entries — the registered kinds and the
                // relationship allow-list describe a host's private domain.
                'Custom types' => self::sizeOf('contacts.types').' registered',
                'Relationship kinds' => self::sizeOf('contacts.relationship_kinds') === 0
                    ? 'FREE-FORM'
                    : self::sizeOf('contacts.relationship_kinds').' allowed',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ContactsManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // `Contact::search()` compiles through the toolkit's `whereLikeEscaped` macro,
        // so it must exist before any host query runs. Registration is idempotent —
        // the toolkit guards it with `hasMacro()`.
        $this->registerBlueprintMacros();
    }

    private static function countryCode(): string
    {
        $code = config('contacts.default_country_code');

        return is_string($code) && $code !== '' ? 'SET' : 'NONE';
    }

    private static function verification(): string
    {
        $style = config('contacts.verification.style', 'code');
        $ttl = (int) config('contacts.verification.ttl', 60);

        return (is_string($style) ? $style : 'code').', '.$ttl.'m';
    }

    private static function sizeOf(string $key): int
    {
        $value = config($key, []);

        return is_array($value) ? count($value) : 0;
    }
}
