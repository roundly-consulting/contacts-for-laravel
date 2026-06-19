<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            'name' => fake()->name(),
            'value' => fake()->word(),
            'category' => fake()->boolean() ? fake()->word() : null,
        ];
    }
}
