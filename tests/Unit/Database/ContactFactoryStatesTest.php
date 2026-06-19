<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('produces valid per-kind values', function (): void {
    $email = Contact::factory()->email()->create();
    $phone = Contact::factory()->phone()->create();
    $url = Contact::factory()->url()->create();

    expect(ValidContactValue::passes(ContactType::Email, (string) $email->value))->toBeTrue()
        ->and(ValidContactValue::passes(ContactType::Phone, (string) $phone->value))->toBeTrue()
        ->and(ValidContactValue::passes(ContactType::Url, (string) $url->value))->toBeTrue();
});

it('applies primary verified and address states', function (): void {
    $contact = Contact::factory()->address()->primary()->verified()->create();

    expect($contact->type)->toBe(ContactType::Address)
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->verified_at)->not->toBeNull();
});

it('attaches an owner via forOwner state', function (): void {
    $user = User::create();

    $contact = Contact::factory()->forOwner($user)->create();

    expect($contact->owner_id)->toBe($user->id);
});

it('sets a type via ofType state', function (): void {
    $contact = Contact::factory()->ofType(ContactType::Social)->create();

    expect($contact->type)->toBe(ContactType::Social);
});

it('builds a pending verification state matched by the scope', function (): void {
    $pending = Contact::factory()->email()->pendingVerification()->create();
    Contact::factory()->email()->verified()->create();

    expect($pending->verified_at)->toBeNull()
        ->and($pending->verification_token)->not->toBeNull()
        ->and(Contact::query()->pendingVerification()->count())->toBe(1);
});
