<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RoundlyConsulting\Contacts\Actions\RequestContactVerificationAction;
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;
use RoundlyConsulting\Contacts\Models\Contact;

it('stores only a hash of the token and an expiry', function (): void {
    config()->set('contacts.verification.style', 'code');
    config()->set('contacts.verification.code_length', 6);
    config()->set('contacts.verification.ttl', 30);

    $contact = Contact::factory()->email()->create();

    $token = app(RequestContactVerificationAction::class)->execute($contact);

    expect($token)->toHaveLength(6)
        ->and($contact->verification_token)->not->toBe($token)
        ->and(Hash::check($token, (string) $contact->verification_token))->toBeTrue()
        ->and($contact->verification_expires_at)->not->toBeNull();
});

it('fires ContactVerificationRequested with the plaintext token', function (): void {
    Event::fake([ContactVerificationRequested::class]);

    $contact = Contact::factory()->email()->create();

    $token = app(RequestContactVerificationAction::class)->execute($contact);

    Event::assertDispatched(
        ContactVerificationRequested::class,
        fn (ContactVerificationRequested $event): bool => $event->contact->is($contact) && $event->plainToken === $token,
    );
});

it('generates a random token when style is token', function (): void {
    config()->set('contacts.verification.style', 'token');
    config()->set('contacts.verification.token_length', 16);

    $contact = Contact::factory()->email()->create();

    $token = app(RequestContactVerificationAction::class)->execute($contact);

    expect($token)->toHaveLength(32)
        ->and($token)->toMatch('/^[0-9a-f]+$/');
});
