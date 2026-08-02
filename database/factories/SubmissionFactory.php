<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => null,
            'course_id' => null,
            'semester_id' => null,
            'group_id' => null,
            'submission_task_id' => null,
            'type' => 'assignment',
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'version' => 1,
            'due_date' => now()->addDays(7),
            'open_at' => now()->subDays(3),
            'close_at' => now()->addDays(14),
            'is_late' => false,
            'extension_until' => null,
            'submitted_at' => null,
            'graded_at' => null,
            'resubmission_count' => 0,
            'last_resubmitted_at' => null,
            'instructions_acknowledged_at' => null,
        ];
    }
}
