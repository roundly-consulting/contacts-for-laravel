<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\ContactException;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Exceptions\PrimaryContactConflict;

it('builds an invalid value exception for a type', function (): void {
    $exception = InvalidContactValue::forType(ContactType::Email, 'nope');

    expect($exception)->toBeInstanceOf(ContactException::class)
        ->and($exception->getMessage())->toContain('email');
});

it('builds a primary conflict exception', function (): void {
    $exception = PrimaryContactConflict::requiresOwner();

    expect($exception)->toBeInstanceOf(ContactException::class)
        ->and($exception->getMessage())->toContain('owner');
});
