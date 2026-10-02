<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\PrimaryContactChanged;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * Review findings: `update()` could leave two primaries in a kind (a primary email turned
 * into a phone kept its flag next to the existing primary phone), and deleting or syncing
 * away the primary left the kind with none — `routeNotificationForVonage()` then returned
 * null and Laravel silently skipped SMS.
 *
 * The rule now: at most one primary per owner and kind, always. With `auto_primary` on, a
 * kind that still has contacts keeps a primary — the next by position is promoted when the
 * primary leaves. With it off, nothing is promoted automatically.
 */
function primariesOf(User $user, string $kind): int
{
    return $user->contacts()->ofType($kind)->primary()->count();
}

it('demotes a primary moved into a kind that already has one, and promotes the next in the old kind', function (): void {
    $user = User::create();
    $email = $user->addEmail('first@corp.com');
    $next = $user->addEmail('second@corp.com');
    $phone = $user->addPhone('+421900000001');

    $moved = Contacts::update($email, new ContactData(ContactType::Phone, '+421900000002'));

    expect(primariesOf($user, 'phone'))->toBe(1)
        ->and($phone->fresh()?->is_primary)->toBeTrue()
        ->and($moved->is_primary)->toBeFalse()
        ->and($moved->fresh()?->is_primary)->toBeFalse()
        ->and(primariesOf($user, 'email'))->toBe(1)
        ->and($next->fresh()?->is_primary)->toBeTrue();
});

it('keeps the flag of a primary moved into a kind with no primary', function (): void {
    $user = User::create();
    $email = $user->addEmail('only@corp.com');

    $moved = Contacts::update($email, new ContactData(ContactType::Phone, '+421900000002'));

    expect($moved->fresh()?->is_primary)->toBeTrue()
        ->and(primariesOf($user, 'phone'))->toBe(1)
        ->and(primariesOf($user, 'email'))->toBe(0);
});

it('makes a moved contact primary of its new kind when asked', function (): void {
    $user = User::create();
    $email = $user->addEmail('first@corp.com');
    $next = $user->addEmail('second@corp.com');
    $phone = $user->addPhone('+421900000001');

    $moved = Contacts::update($email, new ContactData(ContactType::Phone, '+421900000002', isPrimary: true));

    expect($moved->fresh()?->is_primary)->toBeTrue()
        ->and($phone->fresh()?->is_primary)->toBeFalse()
        ->and(primariesOf($user, 'phone'))->toBe(1)
        ->and($next->fresh()?->is_primary)->toBeTrue();
});

it('promotes a non-primary moved into an empty kind, as the first of its kind', function (): void {
    $user = User::create();
    $user->addEmail('first@corp.com');
    $second = $user->addEmail('second@corp.com');

    $moved = Contacts::update($second, new ContactData(ContactType::Phone, '+421900000002'));

    expect($moved->fresh()?->is_primary)->toBeTrue()
        ->and(primariesOf($user, 'email'))->toBe(1);
});

it('never promotes on a kind change when auto_primary is off, but still keeps one primary', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();
    $email = $user->addEmail('first@corp.com', primary: true);
    $user->addEmail('second@corp.com');
    $user->addPhone('+421900000001', primary: true);

    Contacts::update($email, new ContactData(ContactType::Phone, '+421900000002'));

    expect(primariesOf($user, 'phone'))->toBe(1)
        ->and(primariesOf($user, 'email'))->toBe(0);
});

it('promotes the next contact when the primary is deleted', function (): void {
    Event::fake([PrimaryContactChanged::class]);
    $user = User::create();
    $primary = $user->addPhone('+421900000001');
    $next = $user->addPhone('+421900000002');

    Contacts::delete($primary);

    expect($user->primaryPhone()?->is($next))->toBeTrue()
        ->and($user->routeNotificationForVonage())->toBe('+421900000002');
    Event::assertDispatched(PrimaryContactChanged::class, fn (PrimaryContactChanged $e): bool => $e->contact->is($next));
});

it('promotes by position, not by insertion order', function (): void {
    $user = User::create();
    $primary = $user->addPhone('+421900000001');
    $user->addContact(new ContactData(ContactType::Phone, '+421900000002', position: 9));
    $front = $user->addContact(new ContactData(ContactType::Phone, '+421900000003', position: 1));

    Contacts::delete($primary);

    expect($user->primaryPhone()?->is($front))->toBeTrue();
});

it('promotes nothing when the primary is deleted and auto_primary is off', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();
    $primary = $user->addPhone('+421900000001', primary: true);
    $user->addPhone('+421900000002');

    Contacts::delete($primary);

    expect($user->primaryPhone())->toBeNull();
});

it('keeps a primary when the only contact of a kind is synced to a new value', function (): void {
    $user = User::create();
    $user->addPhone('+421900000011');

    Contacts::for($user)->sync(ContactType::Phone, [new ContactData(ContactType::Phone, '+421900000022')]);

    expect($user->primaryPhone()?->value)->toBe('+421900000022')
        ->and($user->routeNotificationForVonage())->toBe('+421900000022');
});

it('promotes the first synced item when the primary is synced away', function (): void {
    $user = User::create();
    $user->addPhone('+421900000001');
    $user->addPhone('+421900000002');
    $user->addPhone('+421900000003');

    Contacts::for($user)->sync(ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000003'),
        new ContactData(ContactType::Phone, '+421900000004'),
    ]);

    expect($user->primaryPhone()?->value)->toBe('+421900000003')
        ->and(primariesOf($user, 'phone'))->toBe(1);
});

it('returns the promoted primary from sync, not a stale copy', function (): void {
    $user = User::create();
    $user->addPhone('+421900000001');

    $synced = Contacts::for($user)->sync(ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000002'),
        new ContactData(ContactType::Phone, '+421900000003'),
    ]);

    expect($synced->map(fn (Contact $contact): array => [$contact->value, $contact->is_primary])->all())
        ->toBe([['+421900000002', true], ['+421900000003', false]]);
});

it('restores a deleted primary as a secondary once another was promoted', function (): void {
    $user = User::create();
    $primary = $user->addPhone('+421900000001');
    $next = $user->addPhone('+421900000002');

    Contacts::delete($primary);
    Contact::withTrashed()->findOrFail($primary->getKey())->restore();

    expect(primariesOf($user, 'phone'))->toBe(1)
        ->and($next->fresh()?->is_primary)->toBeTrue()
        ->and($primary->fresh()?->is_primary)->toBeFalse();
});

it('restores a deleted primary as primary when its kind has none', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();
    $primary = $user->addPhone('+421900000001', primary: true);

    Contacts::delete($primary);
    Contact::withTrashed()->findOrFail($primary->getKey())->restore();

    expect($primary->fresh()?->is_primary)->toBeTrue();
});

it('skips promoting an owner-less contact when primaries require an owner', function (): void {
    $primary = Contact::factory()->phone()->primary()->create(['owner_id' => null, 'owner_type' => null]);
    $next = Contact::factory()->phone()->create(['owner_id' => null, 'owner_type' => null]);
    config()->set('contacts.require_owner_for_primary', true);

    Contacts::delete($primary);

    expect($next->fresh()?->is_primary)->toBeFalse();
});

it('promotes among owner-less contacts only', function (): void {
    $user = User::create();
    $owned = $user->addPhone('+421900000009');
    $primary = Contact::factory()->phone()->primary()->create(['owner_id' => null, 'owner_type' => null]);
    $next = Contact::factory()->phone()->create(['owner_id' => null, 'owner_type' => null, 'position' => 3]);

    Contacts::delete($primary);

    expect($next->fresh()?->is_primary)->toBeTrue()
        ->and($owned->fresh()?->is_primary)->toBeTrue();
});
