<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\DeleteContactAction;
use RoundlyConsulting\Contacts\Models\Contact;

it('soft deletes a contact', function (): void {
    $contact = Contact::factory()->create();

    app(DeleteContactAction::class)->execute($contact);

    expect(Contact::query()->count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1);
});
