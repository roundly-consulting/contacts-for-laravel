<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\Actions\SyncContactsAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('creates updates and deletes to match the given set', function (): void {
    $user = User::create();
    $add = app(AddContactAction::class);

    $keep = $add->execute($user, new ContactData(ContactType::Phone, '+421900000001'));
    $add->execute($user, new ContactData(ContactType::Phone, '+421900000002'));

    $result = app(SyncContactsAction::class)->execute($user, ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001', label: 'Mobile'),
        new ContactData(ContactType::Phone, '+421900000003'),
    ]);

    expect($result)->toHaveCount(2)
        ->and($user->contacts()->ofType(ContactType::Phone)->count())->toBe(2);

    $keep->refresh();
    expect($keep->label)->toBe('Mobile');

    $values = $user->contacts()->ofType(ContactType::Phone)->pluck('value')->all();
    expect($values)->toContain('+421900000001')
        ->toContain('+421900000003')
        ->not->toContain('+421900000002');
});

it('assigns positions by input order', function (): void {
    $user = User::create();

    $result = app(SyncContactsAction::class)->execute($user, ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001'),
        new ContactData(ContactType::Phone, '+421900000002'),
    ]);

    expect($result->first()->position)->toBe(0)
        ->and($result->last()->position)->toBe(1);
});
