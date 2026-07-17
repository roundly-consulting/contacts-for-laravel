<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('casts meta to a collection', function (): void {
    $contact = Contact::factory()->create([
        'meta' => collect(['one', 'two']),
    ]);

    expect($contact->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe(['one', 'two']);
});

it('can be associated with an owner', function (): void {
    $user = User::create();

    $contact = new Contact(['name' => 'Test']);
    $contact->owner()->associate($user);
    $contact->save();

    expect($contact->owner)
        ->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

it('can exist without an owner', function (): void {
    $contact = Contact::factory()->create();

    expect($contact->owner)->toBeNull();
});

it('soft deletes a contact', function (): void {
    $contact = Contact::factory()->create();

    $contact->delete();

    expect(Contact::query()->count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1)
        ->and($contact->fresh()->deleted_at)->not->toBeNull();
});

it('scopes contacts to a specific owner', function (): void {
    $user = User::create();

    $withoutOwner = Contact::factory()->create();

    $withOwner = new Contact(['name' => 'With owner']);
    $withOwner->owner()->associate($user);
    $withOwner->save();

    expect(Contact::query()->forOwner($user)->get())
        ->toBeInstanceOf(EloquentCollection::class)
        ->count()->toBe(1)
        ->first()->id->toBe($withOwner->id);

    expect(Contact::query()->withoutOwner()->get())
        ->count()->toBe(1)
        ->first()->id->toBe($withoutOwner->id);
});

it('includes owner-less contacts when requested', function (): void {
    $user = User::create();

    $withoutOwner = Contact::factory()->create();

    $withOwner = new Contact(['name' => 'With owner']);
    $withOwner->owner()->associate($user);
    $withOwner->save();

    $contacts = Contact::query()
        ->forOwner(owner: $user, includeAlsoWithoutOwner: true)
        ->get();

    expect($contacts)
        ->count()->toBe(2)
        ->and($contacts->pluck('id')->all())
        ->toEqualCanonicalizing([$withOwner->id, $withoutOwner->id]);
});

it('scopes contacts to a category', function (): void {
    Contact::factory()->create(['category' => null]);
    Contact::factory()->create(['category' => 'Family']);

    expect(Contact::query()->forCategory('Family')->get())
        ->count()->toBe(1)
        ->first()->category->toBe('Family');

    expect(Contact::query()->withoutCategory()->get())
        ->count()->toBe(1)
        ->first()->category->toBeNull();
});

it('combines owner and category scopes', function (): void {
    $user = User::create();

    $family = new Contact(['name' => 'Mom', 'category' => 'Family']);
    $family->owner()->associate($user);
    $family->save();

    $work = new Contact(['name' => 'Boss', 'category' => 'Work']);
    $work->owner()->associate($user);
    $work->save();

    expect(Contact::query()->forOwner($user)->forCategory('Family')->get())
        ->count()->toBe(1)
        ->first()->id->toBe($family->id);
});

it('casts the new columns', function (): void {
    $contact = Contact::factory()->email()->primary()->verified()->create([
        'position' => 2,
    ]);

    expect($contact->type)->toBe(ContactType::Email)
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->position)->toBe(2)
        ->and($contact->verified_at)->not->toBeNull();
});

it('defaults is_primary to false and position to zero', function (): void {
    $contact = Contact::factory()->create();

    expect($contact->is_primary)->toBeFalse()
        ->and($contact->position)->toBe(0)
        ->and($contact->verified_at)->toBeNull();
});

it('scopes by type', function (): void {
    Contact::factory()->email()->create();
    Contact::factory()->phone()->create();

    expect(Contact::query()->ofType(ContactType::Email)->get())->count()->toBe(1)
        ->and(Contact::query()->ofType('phone')->get())->count()->toBe(1);
});

it('scopes to primary contacts', function (): void {
    Contact::factory()->primary()->create();
    Contact::factory()->create();

    expect(Contact::query()->primary()->get())->count()->toBe(1);
});

it('scopes to verified and unverified contacts', function (): void {
    Contact::factory()->verified()->create();
    Contact::factory()->create();

    expect(Contact::query()->verified()->get())->count()->toBe(1)
        ->and(Contact::query()->verified(false)->get())->count()->toBe(1);
});

it('searches across name, value and label', function (): void {
    Contact::factory()->create(['name' => 'Alice', 'value' => 'alice@example.com', 'label' => 'Work']);
    Contact::factory()->create(['name' => 'Bob', 'value' => 'bob@example.com', 'label' => 'Home']);

    expect(Contact::query()->search('alice')->get())->count()->toBe(1)
        ->and(Contact::query()->search('Home')->get())->count()->toBe(1)
        ->and(Contact::query()->search('')->get())->count()->toBe(2);
});

/**
 * Case-insensitivity, pinned on the NAME column specifically.
 *
 * The test above looks like it already covers this — it searches 'alice' and finds
 * 'Alice'. It doesn't: that row's *value* is 'alice@example.com', already lowercase, so
 * the match lands on `value` and the name comparison is never what makes it pass. The
 * assertion was green on both drivers while the behaviour it names was broken on one.
 *
 * That is the package-toolkit lesson repeated exactly: its `ilike` branch had never once
 * executed in the package's life, because SQLite's `LIKE` is case-insensitive for ASCII
 * and Postgres' is not. Here the value column was hiding the same divergence. So the
 * needle below matches ONLY via `name`, and the value is deliberately unrelated.
 */
it('searches case-insensitively on every column, on every driver', function (): void {
    Contact::factory()->create(['name' => 'Alice', 'value' => 'a@example.com', 'label' => 'Work']);
    Contact::factory()->create(['name' => 'Bob', 'value' => 'b@example.com', 'label' => 'Home']);

    expect(Contact::query()->search('alice')->get())->count()->toBe(1)
        ->and(Contact::query()->search('ALICE')->get())->count()->toBe(1)
        // `label` and `value` too — each column is its own call site.
        ->and(Contact::query()->search('work')->get())->count()->toBe(1)
        ->and(Contact::query()->search('A@EXAMPLE.COM')->get())->count()->toBe(1);
});

/**
 * A user's `%` or `_` is a literal, not a wildcard.
 *
 * `scopeSearch` used to interpolate the term straight into a `LIKE '%…%'` needle with no
 * escape clause, so a host search box for "50%" silently returned every row containing
 * "50", and "snake_case" matched "snakeXcase". Broken on every driver, not just Postgres.
 *
 * The toolkit's `whereLikeEscaped` is what fixes both this and the case divergence above:
 * it escapes the user's wildcards with an explicit `ESCAPE '\'` (SQLite has no default
 * escape character, so a plain LIKE leaves them live) and picks `ilike`/`like` per driver.
 */
it('treats wildcards in a search term as literals', function (): void {
    Contact::factory()->create(['name' => '50% discount line', 'value' => 'x@example.com']);
    Contact::factory()->create(['name' => 'total 50 units', 'value' => 'y@example.com']);
    Contact::factory()->create(['name' => 'snake_case', 'value' => 'p@example.com']);
    Contact::factory()->create(['name' => 'snakeXcase', 'value' => 'q@example.com']);

    expect(Contact::query()->search('50%')->get())->count()->toBe(1)
        ->and(Contact::query()->search('snake_case')->get())->count()->toBe(1);
});

it('orders by position then id', function (): void {
    $second = Contact::factory()->create(['position' => 2]);
    $first = Contact::factory()->create(['position' => 1]);

    expect(Contact::query()->ordered()->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});
