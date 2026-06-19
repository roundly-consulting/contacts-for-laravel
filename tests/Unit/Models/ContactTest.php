<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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
