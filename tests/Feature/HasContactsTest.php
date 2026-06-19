<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('exposes contacts from an owner entity via the trait', function (): void {
    $user = User::create();

    $contact = new Contact(['name' => 'With owner']);
    $contact->owner()->associate($user);
    $contact->save();

    expect($user->contacts)
        ->toBeInstanceOf(EloquentCollection::class)
        ->count()->toBe(1)
        ->first()->id->toBe($contact->id);
});

it('creates contacts through the owner relationship', function (): void {
    $user = User::create();

    $user->contacts()->create([
        'name' => 'Plumber',
        'value' => '+421 900 000 000',
        'category' => 'Utility',
    ]);

    expect($user->contacts()->forCategory('Utility')->get())
        ->count()->toBe(1)
        ->first()->name->toBe('Plumber');
});

it('respects the configured contact model', function (): void {
    config()->set('contacts.model', Contact::class);

    $user = User::create();

    expect($user->contacts()->getModel())->toBeInstanceOf(Contact::class);
});
