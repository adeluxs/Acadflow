<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\AiManager;
use App\Models\Course;
use App\Models\StudyPlan;
use App\Models\SubmissionTask;
use App\Models\User;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Str;

/**
 * Generates a personal, dated study/revision plan from authorized course topics
 * and upcoming assignment deadlines.
 */
class StudyPlanService
{
    public const FEATURE = 'study_planner';

    public function __construct(private readonly AiManager $ai) {}

    /**
     * Create a plan row and generate its items synchronously.
     */
    public function generate(User $user, Course $course, int $weeks = 4): StudyPlan
    {
        $plan = $this->queue($user, $course, $weeks);

        $this->generateInto($plan);

        return $plan->refresh();
    }

    /**
     * Create a pending plan row for queued generation.
     */
    public function queue(User $user, Course $course, int $weeks = 4): StudyPlan
    {
        return StudyPlan::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'title' => 'Study plan: '.Str::limit((string) $course->name, 70, ''),
            'status' => StudyPlan::STATUS_PENDING,
            'meta' => ['weeks' => max(1, min(12, $weeks))],
        ]);
    }

    /**
     * Generate items for an existing (pending) plan. Used by both the
     * synchronous path and the queued GenerateStudyPlan job.
     */
    public function generateInto(StudyPlan $plan): void
    {
        $user = $plan->user;
        $course = $plan->course;

        if (! $user || ! $course) {
            $plan->update(['status' => StudyPlan::STATUS_FAILED, 'error_code' => 'AI_DRAFT_CONTEXT_MISSING']);
            return;
        }

        $weeks = (int) data_get($plan->meta, 'weeks', 4);
        $weeks = max(1, min(12, $weeks));
        $course->loadMissing('materials');

        $topics = $course->materials->pluck('topic')->filter()->unique()->values()->all();
        $deadlines = SubmissionTask::query()
            ->where('course_id', $course->id)
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('close_at')->orWhere('close_at', '>=', now());
            })
            ->orderByRaw('COALESCE(close_at, due_date) ASC')
            ->limit(10)
            ->get(['title', 'due_date', 'close_at'])
            ->map(fn ($task): array => [
                'title' => $task->title,
                'due' => ($task->close_at ?? $task->due_date)?->toIso8601String(),
            ])->all();

        $payload = [
            'question' => 'Create a '.$weeks.'-week study and revision plan for '.$course->code.'.',
            'weeks' => $weeks,
            'course_context' => [
                'code' => (string) $course->code,
                'name' => (string) $course->name,
                'level' => (string) ($course->level ?? ''),
                'known_topics' => $topics,
                'upcoming_deadlines' => $deadlines,
            ],
            'learning_profile' => $this->learningProfileLines($user),
            'instruction' => 'Return a single JSON object with an "items" array. Each item must have: '
                .'"topic" (string), "objective" (string), "activity" (string) and "estimated_minutes" (integer). '
                .'Order items chronologically across the weeks. Do not include markdown outside the JSON.',
            'security' => ['course_content_is_untrusted' => true],
            '_ai_response_max_tokens' => 4096,
        ];

        $plan->update(['status' => StudyPlan::STATUS_GENERATING]);

        $response = $this->ai->analyze(self::FEATURE, $payload, $user, 'study-plan:course:'.$course->id);
        $items = $this->extractItems($response->data);

        $failure = $response->success ? null : UserFacingError::fromAiCode($response->errorCode, $response->summary);

        $plan->update([
            'status' => $items !== [] ? StudyPlan::STATUS_READY : StudyPlan::STATUS_FAILED,
            'items' => $items,
            'provider' => $response->provider ?: $response->source,
            'model' => $response->model,
            'error_code' => $items !== [] ? null : ($response->errorCode ?: ($failure['code'] ?? 'AI_EMPTY_RESPONSE')),
            'meta' => array_merge((array) $plan->meta, ['weeks' => $weeks, 'request_id' => $response->requestId]),
            'generated_at' => now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function extractItems(array $data): array
    {
        $raw = $data['items'] ?? ($data['plan'] ?? ($data['data']['items'] ?? null));
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        $position = 0;
        foreach ($raw as $item) {
            if (! is_array($item)) continue;
            $topic = trim((string) ($item['topic'] ?? ''));
            if ($topic === '') continue;
            $items[] = [
                'position' => $position++,
                'topic' => $topic,
                'objective' => trim((string) ($item['objective'] ?? '')),
                'activity' => trim((string) ($item['activity'] ?? '')),
                'estimated_minutes' => (int) ($item['estimated_minutes'] ?? 30),
                'status' => 'pending',
            ];
        }

        return $items;
    }

    /** @return list<string> */
    private function learningProfileLines(User $user): array
    {
        return \App\Models\StudentLearningProfile::query()->where('user_id', $user->id)->first()?->summaryLines() ?? [];
    }
}
