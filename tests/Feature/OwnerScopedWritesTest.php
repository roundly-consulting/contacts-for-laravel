<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\ContactDeleted;
use RoundlyConsulting\Contacts\Events\ContactUpdated;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Exceptions\ContactException;
use RoundlyConsulting\Contacts\Exceptions\ContactNotOwned;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Tests\Models\Company;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * `Contacts::for($owner)->update/delete/setPrimary` — the owner-checked twins of the bare
 * manager writes. A contact of another owner (another key, or another morph type with the
 * same key) is refused with ContactNotOwned before anything is written or dispatched.
 */
function ownedContact(Model $owner, string $value = 'a@b.com', bool $primary = false): Contact
{
    return Contacts::for($owner)->add(new ContactData(ContactType::Email, $value, isPrimary: $primary));
}

/**
 * Every refusal case: a stranger with the same morph type, and a model of another type that
 * shares the owner's key (Company lives in the users table, so the key is identical).
 */
function stranger(string $case, User $owner): Model
{
    return $case === 'same-type'
        ? User::create()
        : Company::query()->findOrFail($owner->getKey());
}

dataset('strangers', [
    'same type, different key' => ['same-type'],
    'different type, same key' => ['other-type'],
]);

it('updates, deletes and promotes its own contacts', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();
    $book = Contacts::for($user);
    $first = ownedContact($user);
    $second = ownedContact($user, 'c@d.com');

    $updated = $book->update($first, new ContactData(ContactType::Email, 'new@b.com', label: 'Work'));
    $promoted = $book->setPrimary($second);
    $book->delete($first);

    expect($updated->value)->toBe('new@b.com')
        ->and($updated->label)->toBe('Work')
        ->and($promoted->fresh()?->is_primary)->toBeTrue()
        ->and(ContactModel::class()::query()->find($first->getKey()))->toBeNull()
        ->and(ContactModel::class()::withTrashed()->find($first->getKey())?->trashed())->toBeTrue();
});

it('refuses to update another owner\'s contact', function (string $case): void {
    $owner = User::create();
    $contact = ownedContact($owner);
    Event::fake();

    expect(fn () => Contacts::for(stranger($case, $owner))->update($contact, new ContactData(ContactType::Email, 'x@y.com')))
        ->toThrow(ContactNotOwned::class, 'The contact does not belong to the given owner.');

    expect($contact->fresh()?->value)->toBe('a@b.com');
    Event::assertNotDispatched(ContactUpdated::class);
    Event::assertNotDispatched(PrimaryContactChanged::class);
})->with('strangers');

it('refuses to delete another owner\'s contact', function (string $case): void {
    $owner = User::create();
    $contact = ownedContact($owner);
    Event::fake();

    expect(fn () => Contacts::for(stranger($case, $owner))->delete($contact))
        ->toThrow(ContactNotOwned::class);

    expect($contact->fresh()?->trashed())->toBeFalse();
    Event::assertNotDispatched(ContactDeleted::class);
})->with('strangers');

it('refuses to promote another owner\'s contact', function (string $case): void {
    config()->set('contacts.auto_primary', false);
    $owner = User::create();
    $contact = ownedContact($owner);
    Event::fake();

    expect(fn () => Contacts::for(stranger($case, $owner))->setPrimary($contact))
        ->toThrow(ContactNotOwned::class);

    expect($contact->fresh()?->is_primary)->toBeFalse();
    Event::assertNotDispatched(PrimaryContactChanged::class);
})->with('strangers');

it('refuses an owner-less contact and an unsaved owner', function (): void {
    $orphan = ContactModel::class()::query()->create(['type' => 'email', 'value' => 'o@b.com', 'name' => '']);
    $owned = ownedContact(User::create());

    expect(fn () => Contacts::for(User::create())->delete($orphan))->toThrow(ContactNotOwned::class)
        ->and(fn () => Contacts::for(new User)->delete($owned))->toThrow(ContactNotOwned::class)
        ->and($orphan->fresh()?->trashed())->toBeFalse()
        ->and($owned->fresh()?->trashed())->toBeFalse();
});

it('is a ContactException, so existing catch blocks keep catching it', function (): void {
    $contact = ownedContact(User::create());

    try {
        Contacts::for(User::create())->delete($contact);
        $caught = null;
    } catch (ContactException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ContactNotOwned::class);
});

it('compares keys as strings, so string-keyed owners match an equal stored key', function (): void {
    $fake = Contacts::fake();
    $ulid = '01JD0000000000000000000000';
    $owner = new class extends Model
    {
        protected $keyType = 'string';

        public $incrementing = false;
    };
    $owner->setAttribute('id', $ulid);
    $contact = (new Contact)->forceFill(['owner_type' => $owner->getMorphClass(), 'owner_id' => $ulid]);
    $numeric = (new Contact)->forceFill(['owner_type' => (new User)->getMorphClass(), 'owner_id' => '7']);
    $user = (new User)->forceFill(['id' => 7]);

    Contacts::for($owner)->setPrimary($contact);
    Contacts::for($user)->delete($numeric);

    $other = clone $owner;
    $other->setAttribute('id', '01JD0000000000000000000001');

    expect(fn () => Contacts::for($other)->setPrimary($contact))->toThrow(ContactNotOwned::class);
    $fake->assertPrimarySet($contact);
    $fake->assertDeleted($numeric);
});

it('records owner-checked writes on the fake, and nothing on refusal', function (): void {
    $owner = User::create();
    $contact = ownedContact($owner);
    $fake = Contacts::fake();
    $book = Contacts::for($owner);
    $stranger = Contacts::for(User::create());

    foreach ([
        fn () => $stranger->update($contact, new ContactData(ContactType::Email, 'x@y.com')),
        fn () => $stranger->delete($contact),
        fn () => $stranger->setPrimary($contact),
    ] as $refused) {
        expect($refused)->toThrow(ContactNotOwned::class);
    }

    $fake->assertNothingUpdated();
    $fake->assertNothingDeleted();
    $fake->assertNothingPrimarySet();

    $book->update($contact, new ContactData(ContactType::Email, 'x@y.com'));
    $book->delete($contact);
    $book->setPrimary($contact);

    $fake->assertUpdated($contact, fn (ContactData $data): bool => $data->value === 'x@y.com');
    $fake->assertDeleted($contact);
    $fake->assertPrimarySet($contact);
});

it('leaves the bare manager writes unchecked', function (): void {
    $contact = ownedContact(User::create());

    Contacts::delete($contact);

    expect($contact->fresh()?->trashed())->toBeTrue();
});
