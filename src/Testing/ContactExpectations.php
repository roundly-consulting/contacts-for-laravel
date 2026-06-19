<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Registers Pest expectation matchers for asserting an owner model's contacts.
 * Host applications call {@see ContactExpectations::register()} from their
 * tests/Pest.php:
 *
 *     use RoundlyConsulting\Contacts\Testing\ContactExpectations;
 *     ContactExpectations::register();
 *
 * Matchers operate on an owner model:
 *
 *     expect($company)
 *         ->toHaveContactOfType(ContactType::Email)
 *         ->toHavePrimaryEmail('hello@acme.test')
 *         ->toHavePrimaryContact(ContactType::Phone, '+421900000000')
 *         ->toHaveVerifiedContact('hello@acme.test');
 *
 * The matchers are only defined when Pest's expectation API is available, so
 * this file never pulls Pest into the package's runtime and static analysis
 * stays clean.
 */
final class ContactExpectations
{
    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toHaveContactOfType', function (ContactType|string $type): mixed {
            /** @var Model $owner */
            $owner = $this->value;

            expect(ContactExpectations::query($owner)->ofType($type)->exists())->toBeTrue();

            return $this;
        });

        expect()->extend('toHavePrimaryContact', function (ContactType|string $type, ?string $value = null): mixed {
            /** @var Model $owner */
            $owner = $this->value;

            $primary = ContactExpectations::query($owner)->ofType($type)->primary()->ordered()->first();

            expect($primary)->not->toBeNull();

            if ($value !== null) {
                $kind = $type instanceof ContactType ? $type : ContactType::fromValueOrCustom($type);

                expect($primary?->value)->toBe($kind->normalize($value));
            }

            return $this;
        });

        expect()->extend('toHavePrimaryEmail', function (?string $value = null): mixed {
            /** @var Model $owner */
            $owner = $this->value;

            $primary = ContactExpectations::query($owner)->ofType(ContactType::Email)->primary()->ordered()->first();

            expect($primary)->not->toBeNull();

            if ($value !== null) {
                expect($primary?->value)->toBe(ContactType::Email->normalize($value));
            }

            return $this;
        });

        expect()->extend('toHaveVerifiedContact', function (string $value): mixed {
            /** @var Model $owner */
            $owner = $this->value;

            $matched = ContactExpectations::query($owner)
                ->verified()
                ->get()
                ->contains(fn (Contact $contact): bool => $contact->value === $contact->type->normalize($value));

            expect($matched)->toBeTrue();

            return $this;
        });
    }

    /**
     * @return Builder<Contact>
     */
    public static function query(Model $owner): Builder
    {
        /** @var class-string<Contact> $model */
        $model = config('contacts.model', Contact::class);

        return $model::query()->forOwner($owner);
    }
}
