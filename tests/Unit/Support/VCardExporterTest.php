<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\VCardExporter;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('emits a line per supported kind', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com', label: 'Work');
    $user->addPhone('+421900000000', label: 'Mobile');
    $user->addUrl('example.com');
    $user->addAddress('12 Main St');

    $vcard = VCardExporter::forOwner($user);

    expect($vcard)->toContain('BEGIN:VCARD')
        ->toContain('VERSION:3.0')
        ->toContain('FN:Jane Doe')
        ->toContain('EMAIL;TYPE=Work:jane@example.com')
        ->toContain('TEL;TYPE=Mobile:+421900000000')
        ->toContain('URL:https://example.com')
        ->toContain('ADR:;;12 Main St;;;;')
        ->toContain('END:VCARD');
});

it('escapes separators and skips empty values', function (): void {
    $contact = new Contact([
        'type' => ContactType::Address,
        'name' => 'X',
        'value' => 'A, B; C',
        'label' => 'Home',
    ]);

    expect($contact->toVCard())->toContain('ADR;TYPE=Home:;;A\, B\; C;;;;');

    $empty = new Contact(['type' => ContactType::Email, 'name' => 'X', 'value' => null]);
    expect($empty->toVCard())->not->toContain('EMAIL');
});

it('falls back to a default name', function (): void {
    $contact = new Contact(['type' => ContactType::Custom, 'name' => 'X', 'value' => 'note']);

    expect($contact->toVCard())->toContain('FN:Contact')
        ->toContain('NOTE:note');
});
