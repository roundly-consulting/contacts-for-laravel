<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Contacts\Exceptions\RelationshipException;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;

it('creates, queries and removes a contact-to-contact edge', function (): void {
    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    $person->connectTo($company, ['employee']);

    expect($person->isConnectedTo($company))->toBeTrue()
        ->and($person->connectablesOfType(Contact::class))->toHaveCount(1);

    $person->disconnectFrom($company);

    expect($person->fresh()->isConnectedTo($company))->toBeFalse();
});

it('round-trips a typed relationship kind through meta', function (): void {
    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    $person->relateTo($company, 'works_at');

    $related = $person->relationsOfKind('works_at');

    expect($related)->toHaveCount(1)
        ->and($related->first()->is($company))->toBeTrue()
        ->and($person->relationsOfKind('spouse_of'))->toHaveCount(0);
});

it('transitions status from invite to accept', function (): void {
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();

    $connection = $a->inviteConnection($b);
    expect($connection->status)->toBe(ConnectionStatus::Pending);

    $accepted = $b->acceptConnectionFrom($a);
    expect($accepted->status)->toBe(ConnectionStatus::Accepted);
});

it('resolves a shared contact for multiple owners', function (): void {
    $ownerA = Contact::factory()->create();
    $ownerB = Contact::factory()->create();
    $shared = Contact::factory()->create();

    $ownerA->connectTo($shared);
    $ownerB->connectTo($shared);

    expect(Contacts::sharedWith($ownerA))->toHaveCount(1)
        ->and(Contacts::sharedWith($ownerA)->first()->is($shared))->toBeTrue()
        ->and(Contacts::sharedWith($ownerB))->toHaveCount(1);
});

it('rejects an unknown kind when an allow-list is configured', function (): void {
    config()->set('contacts.relationship_kinds', ['works_at' => 'Works at']);

    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    $person->relateTo($company, 'not_a_kind');
})->throws(RelationshipException::class);

it('allows a configured kind from the allow-list', function (): void {
    config()->set('contacts.relationship_kinds', ['works_at' => 'Works at']);

    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    $person->relateTo($company, 'works_at');

    expect($person->relationsOfKind('works_at'))->toHaveCount(1);
});

it('guards against relating a contact to itself', function (): void {
    $person = Contact::factory()->create();

    $person->relateTo($person, 'works_at');
})->throws(RelationshipException::class);

it('is idempotent for a duplicate relateTo', function (): void {
    $person = Contact::factory()->create();
    $company = Contact::factory()->create();

    $person->relateTo($company, 'works_at');
    $person->relateTo($company, 'works_at');

    expect($person->connections()->count())->toBe(1);
});

it('surfaces expiring connections', function (): void {
    $person = Contact::factory()->create();
    $consultant = Contact::factory()->create();

    $person->connectTo($consultant, ['contractor'], now()->addDays(3));

    expect($person->expiringConnections()->count())->toBe(1);
});

/**
 * Chat review C-7: `relationsOfKind()` listed blocked, pending and expired relationships,
 * while every connections listing helper (`connectablesOfType()`, which `sharedWith()` uses)
 * counts only active ones while `connections.enforce_active_on_check` is on.
 */
it('lists only active relations while the connections flag enforces it', function (array $state): void {
    $person = Contact::factory()->create();
    $company = Contact::factory()->create();
    $active = Contact::factory()->create();

    $person->relateTo($company, 'works_at')->forceFill($state)->save();
    $person->relateTo($active, 'works_at');

    expect($person->relationsOfKind('works_at')->modelKeys())->toBe([$active->getKey()])
        ->and($person->connectablesOfType(Contact::class)->modelKeys())->toBe([$active->getKey()]);

    config()->set('connections.enforce_active_on_check', false);

    expect($person->relationsOfKind('works_at')->modelKeys())->toEqualCanonicalizing([$company->getKey(), $active->getKey()]);
})->with([
    'blocked' => [['status' => ConnectionStatus::Blocked]],
    'pending' => [['status' => ConnectionStatus::Pending]],
    'expired' => [['expires_at' => now()->subMinute()]],
]);
