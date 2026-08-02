<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AcademicSessionFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $endDate = (clone $startDate)->modify('+12 months');

        return [
            'uuid' => fake()->uuid(),
            'university_id' => \App\Models\University::factory(),
            'name' => fake()->unique()->numerify('####/####'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_current' => true,
            'is_active' => true,
        ];
    }
}
