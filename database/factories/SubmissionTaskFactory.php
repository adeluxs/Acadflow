<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubmissionTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'course_id' => null,
            'semester_id' => \App\Models\Semester::factory(),
            'created_by' => \App\Models\User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'instructions' => fake()->paragraph(),
            'type' => 'assignment',
            'open_at' => now()->subDays(7),
            'close_at' => now()->addDays(7),
            'due_date' => now()->addDays(14),
            'late_deadline' => now()->addDays(21),
            'allow_late_submissions' => true,
            'max_resubmissions' => 3,
            'allow_group_submissions' => false,
            'min_group_size' => 1,
            'max_group_size' => 1,
            'allowed_file_types' => ['pdf', 'doc', 'docx'],
            'max_file_size_mb' => 10,
            'max_file_count' => 5,
            'min_file_count' => 1,
            'rubric_id' => null,
            'max_score' => 100,
            'require_approval_before_grading' => false,
            'status' => 'published',
            'is_visible_to_students' => true,
            'submission_format' => 'file',
            'max_submissions_per_student' => 3,
            'submission_requirements_json' => null,
            'late_submission_penalty_percent' => 10,
        ];
    }
}
