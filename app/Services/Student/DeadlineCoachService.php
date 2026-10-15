<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Ai\AiManager;
use App\Models\Course;
use App\Models\DeadlineCoachPlan;
use App\Models\StudentLearningProfile;
use App\Models\SubmissionTask;
use App\Models\User;

/**
 * Advisory deadline coach: turns upcoming deadlines into an ordered action plan.
 * Routes through the central AI runtime and stores a reviewable plan.
 */
class DeadlineCoachService
{
    public const FEATURE = 'study_planner';

    public function __construct(private readonly AiManager $ai) {}

    public function generate(User $user, Course $course): DeadlineCoachPlan
    {
        $deadlines = SubmissionTask::query()
            ->where('course_id', $course->id)
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('close_at')->orWhere('close_at', '>=', now());
            })
            ->orderByRaw('COALESCE(close_at, due_date) ASC')
            ->limit(10)
            ->get(['title', 'due_date', 'close_at', 'max_score'])
            ->map(fn ($task): array => [
                'title' => $task->title,
                'due' => ($task->close_at ?? $task->due_date)?->toIso8601String(),
                'max_score' => $task->max_score,
            ])->all();

        $payload = [
            'question' => 'Create an ordered action plan for my upcoming deadlines in '.$course->code.'.',
            'course_context' => [
                'code' => (string) $course->code,
                'name' => (string) $course->name,
                'upcoming_deadlines' => $deadlines,
            ],
            'learning_profile' => StudentLearningProfile::query()->where('user_id', $user->id)->first()?->summaryLines() ?? [],
            'instruction' => 'Return a single JSON object with an "items" array and a "summary" string. '
                .'Each item must have "title" (string), "due" (string or null) and "steps" (array of short strings). '
                .'Order items by urgency. Do not include markdown outside the JSON.',
            'security' => ['course_content_is_untrusted' => true],
            '_ai_response_max_tokens' => 3072,
        ];

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'deadline-coach:course:'.$course->id);

        $items = is_array($response->data['items'] ?? null) ? $response->data['items'] : [];
        $summary = is_string($response->data['summary'] ?? null) ? $response->data['summary'] : $response->summary;

        return DeadlineCoachPlan::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'items' => array_values(array_filter($items, 'is_array')),
            'summary' => is_string($summary) ? $summary : null,
            'provider' => $response->provider ?: $response->source,
            'model' => $response->model,
            'error_code' => ($items !== [] ? null : ($response->errorCode ?: 'AI_EMPTY_RESPONSE')),
            'generated_at' => now(),
        ]);
    }
}
