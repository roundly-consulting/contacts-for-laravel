<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Contacts\Support\AddressDataFactory;

$attributes = ['city' => 'Vienna', 'street' => 'Ring 3', 'postalCode' => '1010', 'countryIso' => 'AT'];

/**
 * Chat review C-11: an array `meta` — what `structuredAddress([...])` and
 * `ContactData::fromArray(['address' => [...]])` callers naturally pass — was dropped to
 * null, because the addresses DTO only takes a Collection.
 */
it('keeps an array meta as a collection', function () use ($attributes): void {
    $data = AddressDataFactory::fromArray([...$attributes, 'meta' => ['floor' => 2]]);

    expect($data->meta)->toBeInstanceOf(Collection::class)
        ->and($data->meta?->get('floor'))->toBe(2);
});

it('passes a collection meta through and leaves a missing or scalar meta null', function () use ($attributes): void {
    $meta = collect(['floor' => 3]);

    expect(AddressDataFactory::fromArray([...$attributes, 'meta' => $meta])->meta)->toBe($meta)
        ->and(AddressDataFactory::fromArray($attributes)->meta)->toBeNull()
        ->and(AddressDataFactory::fromArray([...$attributes, 'meta' => 'floor 2'])->meta)->toBeNull();
});
