<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'department_id' => null,
            'code' => fake()->bothify('CS###'),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'credit_hours' => fake()->numberBetween(1, 4),
            'level' => fake()->randomElement(['100', '200', '300', '400', '500']),
            'semester' => fake()->randomElement(['first', 'second']),
            'type' => fake()->randomElement(['compulsory', 'elective']),
            'max_capacity' => fake()->numberBetween(30, 100),
            'submission_types' => ['assignment', 'project'],
            'pass_mark' => fake()->numberBetween(40, 60),
            'is_active' => true,
        ];
    }
}
