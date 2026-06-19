<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Contacts\Actions\VerifyContactAction;
use RoundlyConsulting\Contacts\Models\Contact;

it('verifies an unverified contact', function (): void {
    $at = Carbon::parse('2026-01-01 12:00:00');
    $contact = Contact::factory()->create();

    $verified = app(VerifyContactAction::class)->execute($contact, $at);

    expect($verified->verified_at->equalTo($at))->toBeTrue();
});

it('is idempotent for an already verified contact', function (): void {
    $original = Carbon::parse('2026-01-01 12:00:00');
    $contact = Contact::factory()->verified()->create(['verified_at' => $original]);

    $verified = app(VerifyContactAction::class)->execute($contact, Carbon::parse('2026-02-02 12:00:00'));

    expect($verified->verified_at->equalTo($original))->toBeTrue();
});

it('defaults to now when no time given', function (): void {
    Carbon::setTestNow('2026-03-03 09:00:00');
    $contact = Contact::factory()->create();

    $verified = app(VerifyContactAction::class)->execute($contact);

    expect($verified->verified_at->equalTo(Carbon::parse('2026-03-03 09:00:00')))->toBeTrue();

    Carbon::setTestNow();
});
