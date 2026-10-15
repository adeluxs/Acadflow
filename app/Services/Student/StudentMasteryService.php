<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Models\Course;
use App\Models\PracticeQuiz;
use App\Models\TopicMastery;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Derives per-topic mastery from the student's own practice attempts.
 * Deterministic; no provider call required.
 */
class StudentMasteryService
{
    /**
     * Recompute mastery rows for a student in a course.
     *
     * @return list<TopicMastery>
     */
    public function recompute(User $user, Course $course): array
    {
        $aggregate = [];

        $quizzes = PracticeQuiz::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('topic')
            ->get();

        foreach ($quizzes as $quiz) {
            $topic = trim((string) $quiz->topic);
            if ($topic === '') {
                continue;
            }

            foreach ($quiz->attempts()->where('user_id', $user->id)->get() as $attempt) {
                foreach ((array) $attempt->answers as $answer) {
                    if (! is_array($answer)) continue;
                    $aggregate[$topic]['total'] = ($aggregate[$topic]['total'] ?? 0) + 1;
                    if (! empty($answer['is_correct'])) {
                        $aggregate[$topic]['correct'] = ($aggregate[$topic]['correct'] ?? 0) + 1;
                    }
                }
            }
        }

        $results = [];
        foreach ($aggregate as $topic => $agg) {
            $total = (int) ($agg['total'] ?? 0);
            $correct = (int) ($agg['correct'] ?? 0);
            $mastery = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

            $results[] = TopicMastery::query()->updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id, 'topic' => $topic],
                ['mastery' => $mastery, 'signals' => ['correct' => $correct, 'total' => $total]],
            );
        }

        return $results;
    }

    /** @return Collection<int,TopicMastery> */
    public function weakTopics(User $user, ?int $courseId = null, int $limit = 6): Collection
    {
        return TopicMastery::query()
            ->where('user_id', $user->id)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->with('course:id,uuid,code,name')
            ->orderBy('mastery')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int,TopicMastery> */
    public function all(User $user, ?int $courseId = null): Collection
    {
        return TopicMastery::query()
            ->where('user_id', $user->id)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->with('course:id,uuid,code,name')
            ->orderBy('topic')
            ->get();
    }
}
