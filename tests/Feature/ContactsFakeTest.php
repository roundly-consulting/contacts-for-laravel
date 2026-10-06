<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\ContactAdded;
use RoundlyConsulting\Contacts\Exceptions\ContactException;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Testing\ContactsFake;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('is what an injected manager receives', function (): void {
    $fake = Contacts::fake();

    expect(app(ContactsManager::class))->toBe($fake)
        ->and($fake)->toBeInstanceOf(ContactsFake::class);
});

it('records adds from the builder, the book and the trait without writing', function (): void {
    Event::fake();
    $user = User::create();
    $fake = Contacts::fake();

    $built = Contacts::for($user)->email('A@B.com')->label('Work')->add();
    Contacts::for($user)->add(new ContactData(ContactType::Phone, '+421900000000'));
    $user->addUrl('example.com');

    $fake->assertAdded();
    $fake->assertAdded(fn (ContactData $data, $owner): bool => $data->type === ContactType::Email && $owner->is($user));
    $fake->assertAdded(fn (ContactData $data): bool => $data->type === ContactType::Url);

    expect(Contact::query()->count())->toBe(0)
        ->and($built->exists)->toBeFalse()
        ->and($built->value)->toBe('a@b.com')
        ->and($built->label)->toBe('Work')
        ->and($built->owner_id)->toBe($user->id);
    Event::assertNotDispatched(ContactAdded::class);
});

it('fails assertAdded when nothing matches', function (): void {
    $fake = Contacts::fake();

    expect(fn () => $fake->assertAdded())->toThrow(AssertionFailedError::class, 'No contact was added.');

    User::create()->addEmail('a@b.com');

    expect(fn () => $fake->assertAdded(fn (ContactData $data): bool => $data->value === 'nope'))
        ->toThrow(AssertionFailedError::class, 'No matching contact was added.');
});

it('asserts nothing was added', function (): void {
    $fake = Contacts::fake();

    $fake->assertNothingAdded();

    User::create()->addPhone('+421900000000');

    expect(fn () => $fake->assertNothingAdded())->toThrow(AssertionFailedError::class, 'added unexpectedly');
});

it('records syncs from the book and the trait book', function (): void {
    $user = User::create();
    $fake = Contacts::fake();

    $fake->assertNothingSynced();

    $result = Contacts::for($user)->sync(ContactType::Phone, [new ContactData(ContactType::Phone, '+421 900 000 000')]);
    $user->contactBook()->sync(ContactType::Email, []);

    $fake->assertSynced();
    $fake->assertSynced(ContactType::Phone, fn (array $items, $owner): bool => count($items) === 1 && $owner->is($user));
    $fake->assertSynced(ContactType::Email);

    expect($result)->toHaveCount(1)
        ->and($result->first()->value)->toBe('+421900000000')
        ->and(Contact::query()->count())->toBe(0)
        ->and(fn () => $fake->assertSynced(ContactType::Url))->toThrow(AssertionFailedError::class, 'No matching contacts were synced.')
        ->and(fn () => $fake->assertNothingSynced())->toThrow(AssertionFailedError::class, 'synced unexpectedly');
});

it('records updates', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNothingUpdated();

    Contacts::update($contact, new ContactData(ContactType::Email, 'new@b.com'));

    $fake->assertUpdated();
    $fake->assertUpdated($contact, fn (ContactData $data): bool => $data->value === 'new@b.com');

    expect($contact->fresh()->value)->not->toBe('new@b.com')
        ->and(fn () => $fake->assertUpdated(Contact::factory()->email()->create()))->toThrow(AssertionFailedError::class, 'No matching contact was updated.')
        ->and(fn () => $fake->assertUpdated($contact, fn (): bool => false))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingUpdated())->toThrow(AssertionFailedError::class, 'updated unexpectedly');
});

it('records deletes', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNothingDeleted();
    expect(fn () => $fake->assertDeleted())->toThrow(AssertionFailedError::class, 'No matching contact was deleted.');

    Contacts::delete($contact);

    $fake->assertDeleted();
    $fake->assertDeleted($contact);

    expect($contact->fresh())->not->toBeNull()
        ->and(fn () => $fake->assertDeleted(Contact::factory()->email()->create()))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingDeleted())->toThrow(AssertionFailedError::class, 'deleted unexpectedly');
});

it('records primary changes', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNothingPrimarySet();
    expect(fn () => $fake->assertPrimarySet())->toThrow(AssertionFailedError::class, 'set as primary');

    Contacts::setPrimary($contact);

    $fake->assertPrimarySet();
    $fake->assertPrimarySet($contact);

    expect(fn () => $fake->assertNothingPrimarySet())->toThrow(AssertionFailedError::class, 'set unexpectedly');
});

it('records contacts marked verified', function (): void {
    $contact = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNothingVerified();
    expect(fn () => $fake->assertVerified())->toThrow(AssertionFailedError::class, 'marked verified');

    Contacts::verification()->markVerified($contact);

    $fake->assertVerified();
    $fake->assertVerified($contact);

    expect($contact->fresh()->verified_at)->toBeNull()
        ->and(fn () => $fake->assertVerified(Contact::factory()->email()->create()))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingVerified())->toThrow(AssertionFailedError::class, 'verified unexpectedly');
});

it('records verification requests from the accessor and the model', function (): void {
    $viaAccessor = Contact::factory()->email()->create();
    $viaModel = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNoVerificationRequested();
    expect(fn () => $fake->assertVerificationRequested())->toThrow(AssertionFailedError::class, 'was requested');

    expect(Contacts::verification()->request($viaAccessor))->toBe('fake-token');
    $viaModel->requestVerification();

    $fake->assertVerificationRequested($viaAccessor);
    $fake->assertVerificationRequested($viaModel);

    expect(fn () => $fake->assertVerificationRequested(Contact::factory()->email()->create()))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNoVerificationRequested())->toThrow(AssertionFailedError::class, 'requested unexpectedly');
});

it('records verification confirmations from the accessor and the model', function (): void {
    $viaAccessor = Contact::factory()->email()->create();
    $viaModel = Contact::factory()->email()->create();
    $fake = Contacts::fake();

    $fake->assertNoVerificationConfirmed();
    expect(fn () => $fake->assertVerificationConfirmed())->toThrow(AssertionFailedError::class, 'was confirmed');

    Contacts::verification()->confirm($viaAccessor, 'any');
    $viaModel->confirmVerification('any');

    $fake->assertVerificationConfirmed();
    $fake->assertVerificationConfirmed($viaAccessor);
    $fake->assertVerificationConfirmed($viaModel);

    expect(fn () => $fake->assertVerificationConfirmed(Contact::factory()->email()->create()))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNoVerificationConfirmed())->toThrow(AssertionFailedError::class, 'confirmed unexpectedly');
});

it('still reads from the database under the fake', function (): void {
    $user = User::create();
    $stored = $user->addEmail('a@b.com');
    Contacts::fake();

    expect($user->primaryEmail()?->is($stored))->toBeTrue()
        ->and(Contacts::for($user)->all())->toHaveCount(1);
});

/**
 * Chat review C-5: the fake accepted what the real manager refuses (an invalid value, a
 * structured address on a non-address kind), never applied first-of-kind `auto_primary`,
 * gave every synced item position 0 and kept each item's own kind. Each scenario runs once
 * against the real manager and once under the fake, for two owners in the same stored state,
 * and must come out the same: the same exception, or the same kind, value, primary flag and
 * position for every contact handed back.
 */
function fakeParityOutcome(Closure $scenario, User $owner): array|string
{
    try {
        $result = $scenario($owner);
    } catch (ContactException $exception) {
        return $exception::class;
    }

    return collect(is_iterable($result) ? $result : [$result])
        ->map(fn (Contact $contact): array => [$contact->kind, $contact->value, $contact->is_primary, $contact->position])
        ->values()
        ->all();
}

it('behaves like the real manager under the fake', function (Closure $setup, Closure $scenario): void {
    $real = User::create();
    $faked = User::create();
    $setup($real);
    $setup($faked);

    $expected = fakeParityOutcome($scenario, $real);
    Contacts::fake();

    expect(fakeParityOutcome($scenario, $faked))->toBe($expected);
})->with([
    'an invalid value is refused' => [
        fn (User $owner) => null,
        fn (User $owner) => Contacts::for($owner)->email('not-an-email')->add(),
    ],
    'a structured address on an email is refused' => [
        fn (User $owner) => null,
        fn (User $owner) => Contacts::for($owner)->add(new ContactData(
            ContactType::Email,
            'a@x.test',
            address: AddressData::make(city: 'Vienna', street: 'Ring 3', postalCode: '1010', countryIso: 'AT'),
        )),
    ],
    'the first of a kind becomes primary, the next does not' => [
        fn (User $owner) => null,
        fn (User $owner) => [$owner->addPhone('+421900000001'), $owner->addPhone('+421 900 000 002')],
    ],
    'a kind that already has a primary keeps it' => [
        fn (User $owner) => $owner->addPhone('+421900000001'),
        fn (User $owner) => $owner->addPhone('+421900000002'),
    ],
    'auto_primary off promotes nothing' => [
        fn (User $owner) => config()->set('contacts.auto_primary', false),
        fn (User $owner) => $owner->addEmail('a@x.test'),
    ],
    'a structured address renders into the value' => [
        fn (User $owner) => null,
        fn (User $owner) => Contacts::for($owner)->structuredAddress(['city' => 'Vienna', 'street' => 'Ring 3', 'postalCode' => '1010', 'countryIso' => 'AT'])->add(),
    ],
    'sync coerces the kind and numbers positions by input order' => [
        fn (User $owner) => null,
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Url, [
            new ContactData(ContactType::Url, 'a.test'),
            new ContactData(ContactType::Email, 'b.test'),
        ]),
    ],
    'sync refuses an invalid item' => [
        fn (User $owner) => null,
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Email, [new ContactData(ContactType::Email, 'nope')]),
    ],
    'sync keeps a matched primary' => [
        fn (User $owner) => [$owner->addEmail('a@x.test'), $owner->addEmail('b@x.test')],
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Email, [
            new ContactData(ContactType::Email, 'b@x.test'),
            new ContactData(ContactType::Email, 'a@x.test'),
        ]),
    ],
    'sync promotes the first item when the primary is synced away' => [
        fn (User $owner) => $owner->addEmail('a@x.test'),
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Email, [
            new ContactData(ContactType::Email, 'b@x.test'),
            new ContactData(ContactType::Email, 'c@x.test'),
        ]),
    ],
    'sync hands the primary to a flagged item' => [
        fn (User $owner) => $owner->addEmail('a@x.test'),
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Email, [
            new ContactData(ContactType::Email, 'a@x.test'),
            new ContactData(ContactType::Email, 'b@x.test', isPrimary: true),
        ]),
    ],
    'sync of a kind that has no primary promotes nothing' => [
        function (User $owner): void {
            config()->set('contacts.auto_primary', false);
            $owner->addEmail('a@x.test');
            config()->set('contacts.auto_primary', true);
        },
        fn (User $owner) => Contacts::for($owner)->sync(ContactType::Email, [new ContactData(ContactType::Email, 'b@x.test')]),
    ],
    'sync of a registered custom kind' => [
        fn (User $owner) => config()->set('contacts.types', ['whatsapp' => ['rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/']]]),
        fn (User $owner) => Contacts::for($owner)->sync('whatsapp', [new ContactData(ContactType::Custom, '+421 900 000 003')]),
    ],
]);
