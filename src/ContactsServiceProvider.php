<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Closure;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\ContactsConfig;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
                'Table' => self::orInvalid(static fn (): string => ContactsConfig::table() ?? 'contacts'),
                'Auto primary' => Config::boolean('contacts.auto_primary', true) ? 'ON' : 'OFF',
                'Require owner for primary' => Config::boolean('contacts.require_owner_for_primary') ? 'ON' : 'OFF',
                'Default country code' => self::countryCode(),
                'Verification' => self::verification(),
                // Counts, never the entries — the registered kinds and the
                // relationship allow-list describe a host's private domain.
                'Custom types' => self::orInvalid(static fn (): string => count(ContactsConfig::types()).' registered'),
                'Relationship kinds' => self::orInvalid(static fn (): string => ContactsConfig::relationshipKinds() === []
                    ? 'FREE-FORM'
                    : count(ContactsConfig::relationshipKinds()).' allowed'),
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
        return self::orInvalid(static fn (): string => ContactsConfig::defaultCountryCode() === null ? 'NONE' : 'SET');
    }

    private static function verification(): string
    {
        return self::orInvalid(static fn (): string => ContactsConfig::verificationStyle().', '
            .ContactsConfig::verificationTtl().'m, '
            .ContactsConfig::verificationMaxAttempts().' attempts');
    }

    /**
     * The value a strict read produces, or INVALID when the host's config is malformed:
     * `php artisan about` keeps rendering on a broken host, while the real read path throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }
}
