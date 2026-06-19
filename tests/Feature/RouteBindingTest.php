<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Contacts\Models\Contact;

it('resolves a contact via implicit route-model binding', function (): void {
    Route::middleware(SubstituteBindings::class)
        ->get('/contacts/{contact}', fn (Contact $contact): string => (string) $contact->getKey());

    $contact = Contact::factory()->email()->create();

    $response = $this->get('/contacts/'.$contact->getKey());

    $response->assertOk();
    expect($response->getContent())->toBe((string) $contact->getKey());
});
