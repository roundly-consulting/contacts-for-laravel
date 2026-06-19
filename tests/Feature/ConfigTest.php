<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('ships sensible defaults', function (): void {
    expect(config('contacts.auto_primary'))->toBeTrue()
        ->and(config('contacts.require_owner_for_primary'))->toBeFalse()
        ->and(config('contacts.table'))->toBe('contacts');
});

it('honors auto_primary toggle', function (): void {
    config()->set('contacts.auto_primary', false);
    $user = User::create();

    $contact = $user->addEmail('a@b.com');

    expect($contact->is_primary)->toBeFalse();
});

it('resolves custom kinds from config', function (): void {
    config()->set('contacts.types.whatsapp', [
        'label' => 'WhatsApp',
        'icon' => 'chat',
    ]);

    $type = ContactType::fromValueOrCustom('whatsapp');

    expect($type)->toBe(ContactType::Custom);
});

it('uses the configured table name via the model', function (): void {
    $contact = new Contact;

    expect($contact->getTable())->toBe('contacts');
});

it('prefers an explicit table property over config', function (): void {
    $contact = new Contact;
    $contact->setTable('custom_contacts');

    expect($contact->getTable())->toBe('custom_contacts');
});

it('falls back to the conventional table when no config table', function (): void {
    config()->set('contacts.table', null);

    expect((new Contact)->getTable())->toBe('contacts');
});
