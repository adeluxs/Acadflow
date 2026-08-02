<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SemesterFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $endDate = (clone $startDate)->modify('+4 months');

        return [
            'uuid' => fake()->uuid(),
            'academic_session_id' => \App\Models\AcademicSession::factory(),
            'name' => fake()->randomElement(['First', 'Second']).' Semester',
            'number' => fake()->numberBetween(1, 2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'grading_deadline' => $endDate,
            'is_active' => true,
        ];
    }
}
