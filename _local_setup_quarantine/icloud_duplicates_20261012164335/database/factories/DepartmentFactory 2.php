<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'faculty_id' => \App\Models\Faculty::factory(),
            'name' => fake()->words(2, true),
            'short_name' => fake()->bothify('???'),
            'code' => fake()->bothify('???'),
            'head_id' => null,
            'is_active' => true,
        ];
    }
}
