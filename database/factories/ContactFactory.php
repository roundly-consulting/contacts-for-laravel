<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<model-property<Contact>, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContactType::Custom,
            'name' => fake()->name(),
            'value' => fake()->word(),
            'label' => fake()->boolean() ? fake()->word() : null,
            'category' => fake()->boolean() ? fake()->word() : null,
            'is_primary' => false,
            'position' => 0,
            'verified_at' => null,
        ];
    }

    public function email(): self
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Email,
            'value' => fake()->safeEmail(),
        ]);
    }

    public function phone(): self
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Phone,
            'value' => '+'.fake()->numberBetween(1, 9).fake()->numerify('#########'),
        ]);
    }

    public function url(): self
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Url,
            'value' => fake()->url(),
        ]);
    }

    public function address(): self
    {
        return $this->state(fn (): array => [
            'type' => ContactType::Address,
            'value' => fake()->address(),
        ]);
    }

    public function primary(): self
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
        ]);
    }

    public function verified(): self
    {
        return $this->state(fn (): array => [
            'verified_at' => now(),
        ]);
    }

    public function forOwner(Model $owner): self
    {
        return $this->state(fn (): array => [
            'owner_id' => $owner->getKey(),
            'owner_type' => $owner->getMorphClass(),
        ]);
    }

    public function ofType(ContactType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
