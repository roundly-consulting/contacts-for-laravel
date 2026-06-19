<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Tests\Models\User;

it('routes mail to the primary email', function (): void {
    $user = User::create();
    $user->addEmail('hello@acme.test', primary: true);

    expect($user->routeNotificationForMail())->toBe('hello@acme.test');
});

it('routes vonage and twilio to the primary phone', function (): void {
    $user = User::create();
    $user->addPhone('+421900000000', primary: true);

    expect($user->routeNotificationForVonage())->toBe('+421900000000')
        ->and($user->routeNotificationForTwilio())->toBe('+421900000000');
});

it('returns null when no primary contact of the kind exists', function (): void {
    $user = User::create();

    expect($user->routeNotificationForMail())->toBeNull()
        ->and($user->routeNotificationForVonage())->toBeNull()
        ->and($user->routeNotificationForTwilio())->toBeNull();
});

it('uses the primary email over a non-primary one', function (): void {
    $user = User::create();
    $user->addEmail('first@acme.test', primary: true);
    $user->addEmail('second@acme.test');

    expect($user->routeNotificationForMail())->toBe('first@acme.test');
});
