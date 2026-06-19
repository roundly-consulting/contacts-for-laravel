<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\Actions\ConfirmContactVerificationAction;
use RoundlyConsulting\Contacts\Actions\RequestContactVerificationAction;
use RoundlyConsulting\Contacts\Events\ContactVerified;
use RoundlyConsulting\Contacts\Exceptions\InvalidVerificationToken;
use RoundlyConsulting\Contacts\Exceptions\VerificationExpired;
use RoundlyConsulting\Contacts\Models\Contact;

it('verifies a contact with the correct token and clears token fields', function (): void {
    Event::fake([ContactVerified::class]);

    $contact = Contact::factory()->email()->create();
    $token = app(RequestContactVerificationAction::class)->execute($contact);

    $confirmed = app(ConfirmContactVerificationAction::class)->execute($contact, $token);

    expect($confirmed->verified_at)->not->toBeNull()
        ->and($confirmed->verification_token)->toBeNull()
        ->and($confirmed->verification_expires_at)->toBeNull();

    Event::assertDispatched(ContactVerified::class);
});

it('rejects an incorrect token', function (): void {
    $contact = Contact::factory()->email()->create();
    app(RequestContactVerificationAction::class)->execute($contact);

    app(ConfirmContactVerificationAction::class)->execute($contact, 'wrong-token');
})->throws(InvalidVerificationToken::class);

it('rejects when no token was requested', function (): void {
    $contact = Contact::factory()->email()->create();

    app(ConfirmContactVerificationAction::class)->execute($contact, '123456');
})->throws(InvalidVerificationToken::class);

it('rejects an expired token', function (): void {
    config()->set('contacts.verification.ttl', 10);

    $contact = Contact::factory()->email()->create();
    $token = app(RequestContactVerificationAction::class)->execute($contact);

    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    try {
        app(ConfirmContactVerificationAction::class)->execute($contact, $token);
        $this->fail('Expected VerificationExpired.');
    } catch (VerificationExpired) {
        expect(true)->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('is idempotent for an already verified contact', function (): void {
    $contact = Contact::factory()->email()->verified()->create();

    $result = app(ConfirmContactVerificationAction::class)->execute($contact, 'anything');

    expect($result->is($contact))->toBeTrue();
});

it('exposes verification sugar on the model', function (): void {
    $contact = Contact::factory()->email()->create();

    $token = $contact->requestVerification();
    $contact->confirmVerification($token);

    expect($contact->isVerified())->toBeTrue();
});
