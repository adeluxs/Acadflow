<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Models\Course;
use App\Models\FlashcardReview;
use App\Models\PracticeAttempt;
use App\Models\ReadinessSnapshot;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\TopicMastery;
use App\Models\User;

/**
 * Deterministic exam-readiness estimator from stored student signals.
 * No provider call is required; the score is explainable and reproducible.
 */
class ReadinessService
{
    public function compute(User $user, Course $course): ReadinessSnapshot
    {
        $topics = TopicMastery::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get();

        $masteryAvg = $topics->isNotEmpty() ? (int) round((float) $topics->avg('mastery')) : 0;

        $attempts = PracticeAttempt::query()
            ->where('user_id', $user->id)
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $course->id))
            ->get();

        $scored = (int) $attempts->sum('score');
        $totalQuestions = (int) $attempts->sum('total');
        $practicePct = $totalQuestions > 0 ? (int) round(($scored / $totalQuestions) * 100) : 0;

        $tasksTotal = SubmissionTask::query()
            ->where('course_id', $course->id)
            ->where('status', 'published')
            ->count();

        $tasksDone = Submission::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('submission_task_id')
            ->distinct('submission_task_id')
            ->count('submission_task_id');

        $deadlinePct = $tasksTotal > 0 ? (int) round(min(1.0, $tasksDone / $tasksTotal) * 100) : 0;

        $reviews = FlashcardReview::query()->where('user_id', $user->id)->count();

        $score = (int) round(($masteryAvg * 0.4) + ($practicePct * 0.4) + ($deadlinePct * 0.2));

        return ReadinessSnapshot::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'score' => max(0, min(100, $score)),
            'signals' => [
                'mastery' => $masteryAvg,
                'practice' => $practicePct,
                'deadlines' => $deadlinePct,
                'flashcard_reviews' => $reviews,
            ],
            'generated_at' => now(),
        ]);
    }

    public function latest(User $user, ?int $courseId = null): ?ReadinessSnapshot
    {
        return ReadinessSnapshot::query()
            ->where('user_id', $user->id)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->latest('generated_at')
            ->first();
    }
}
