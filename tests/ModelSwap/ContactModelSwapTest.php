<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Fixtures\CustomContactModel;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * The model-swap proof (S) for `contacts.model`, driven through the REAL flows rather
 * than a resolver string check.
 *
 * `tests/Unit/Support/ContactModelTest.php` already pins that the resolver *validates*
 * what it is handed (it rejects a non-model and a bad class string) — that is domain
 * behaviour and stays where it is. What it cannot prove is the thing this file exists
 * for: that the package actually *uses* the configured class when a host adds a contact.
 * It sets `contacts.model` inside the test body and asserts a class-string, so it would
 * stay green with every observer and relation still hung on the packaged model
 * (media #28) — and a body-time swap is not what a host does anyway. A host sets this in
 * `config/contacts.php`, i.e. before boot, which is what {@see SwappedContactTestCase}
 * (this directory's base case) reproduces.
 */
it('honours a host contact model through the add flow', function (): void {
    expect('contacts.model')->toHonourModelSwap(CustomContactModel::class, function (): array {
        $user = User::create(['name' => 'Ada']);

        // The flows a host actually calls — the typed helpers and the generic builder.
        $email = $user->addEmail('ada@example.com', 'work');
        $phone = $user->addPhone('+421900000000', 'mobile');

        return [
            $email,
            $phone,
            // The morph relation hydrates through the seam too, not just the writes.
            ...$user->contacts()->get()->all(),
        ];
    });
});

/**
 * The seam must survive the reads a host depends on most: the primary-contact lookup and
 * the type-scoped query. If these resolved the packaged model while the writes went
 * through the host's, the answer would come from a different class than the one that
 * wrote it — and none of the host's model events would ever fire.
 */
it('answers primary and type-scoped lookups through the swapped model', function (): void {
    $user = User::create(['name' => 'Grace']);

    $user->addEmail('grace@example.com', 'work');

    expect($user->primaryContact(ContactType::Email))->toBeInstanceOf(CustomContactModel::class)
        ->and($user->contactsOfType(ContactType::Email)->first())->toBeInstanceOf(CustomContactModel::class)
        ->and($user->contacts()->first())->toBeInstanceOf(CustomContactModel::class);
});

/**
 * Chat review C-13: the factory the package ships is part of the seam. A host test seeding
 * through it must get rows made as its own model — counted on the subclass, so its events
 * and casts ran — whether it calls the factory on the subclass or on the packaged model.
 */
it('builds the host contact model from the packaged factory', function (): void {
    expect('contacts.model')->toHonourModelSwap(CustomContactModel::class, function (): array {
        $owner = User::create(['name' => 'Ada']);

        return [
            CustomContactModel::factory()->email()->forOwner($owner)->create(),
            Contact::factory()->phone()->forOwner($owner)->create(),
        ];
    });
});

// The structural half of the seam — `Contact` is non-final, and `contacts.model` really
// defaults to the packaged model — is pinned once in tests/Unit/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
