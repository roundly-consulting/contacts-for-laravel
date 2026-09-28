<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * A host that wires the switches to env() hands the package strings: `(bool) 'false'` is
 * true, so `CONTACTS_AUTO_PRIMARY=false` used to leave auto-primary ON.
 */
it('reads auto_primary env strings as booleans', function (string $value, bool $primary): void {
    config()->set('contacts.auto_primary', $value);

    expect(User::create()->addEmail('a@b.com')->is_primary)->toBe($primary);
})->with([
    ['false', false],
    ['0', false],
    ['off', false],
    ['true', true],
    ['1', true],
]);

it('reports the switches by their boolean meaning in about', function (): void {
    config()->set('contacts.auto_primary', 'false');
    config()->set('contacts.require_owner_for_primary', 'true');

    Artisan::call('about', ['--only' => 'contacts', '--json' => true]);
    $about = json_decode(Artisan::output(), true);

    expect($about['contacts']['auto_primary'] ?? null)->toBe('OFF')
        ->and($about['contacts']['require_owner_for_primary'] ?? null)->toBe('ON');
});
