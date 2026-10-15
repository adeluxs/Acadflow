<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UniversityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->words(2, true),
            'short_name' => fake()->bothify('???'),
            'code' => fake()->bothify('???'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'logo' => null,
            'website' => fake()->url(),
            'timezone' => 'UTC',
            'is_active' => true,
            'settings' => null,
        ];
    }
}
