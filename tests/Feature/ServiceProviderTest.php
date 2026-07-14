<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Contacts\Facades\Contacts;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'contacts-config',
    'contacts-migrations',
    'contacts-translations',
]);

it('never auto-loads its migrations', function (): void {
    /** @var Migrator $migrator */
    $migrator = app('migrator');

    expect($migrator->paths())
        ->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('publishes the migration into the host under a timestamped name', function (): void {
    $paths = ServiceProvider::pathsToPublish(ContactsServiceProvider::class, 'contacts-migrations');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);
    $destination = (string) reset($paths);

    expect($source)->toEndWith('database/migrations/create_contacts_table.php')
        ->and($destination)->toStartWith(database_path('migrations').DIRECTORY_SEPARATOR)
        ->and(basename($destination))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_contacts_table\.php$/');
});

it('loads the package translations', function (): void {
    expect(__('contacts::errors.invalid_verification_token'))
        ->toBe('The verification token is invalid.');
});

it('binds the contacts manager as a singleton', function (): void {
    expect(app(ContactsManager::class))->toBe(app(ContactsManager::class));
});

it('registers the Contacts facade alias', function (): void {
    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Contacts', Contacts::class);
});

it('contributes a contacts section to about', function (string $expected): void {
    $this->artisan('about --only=contacts')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Contacts',
    'Model',
    'Auto primary',
    'Require owner for primary',
    'Default country code',
    'Verification',
    'FREE-FORM',
]);

it('reports registered kinds as counts and never leaks their contents', function (): void {
    config()->set('contacts.types', ['whatsapp' => ['label' => 'WhatsApp']]);
    config()->set('contacts.relationship_kinds', ['works_at' => 'Works at', 'spouse_of' => 'Spouse of']);
    config()->set('contacts.default_country_code', '421');

    $this->artisan('about --only=contacts')
        ->expectsOutputToContain('1 registered')
        ->expectsOutputToContain('2 allowed')
        ->doesntExpectOutputToContain('whatsapp')
        ->doesntExpectOutputToContain('works_at')
        ->doesntExpectOutputToContain('421')
        ->assertExitCode(0);
});
