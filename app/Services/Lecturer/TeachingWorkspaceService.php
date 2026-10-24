<?php

declare(strict_types=1);

namespace App\Services\Lecturer;

use App\Models\AiContentDraft;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeachingWorkspaceService
{
    public function courses(User $lecturer): Builder
    {
        return Course::query()
            ->whereHas('lecturerAssignments', fn (Builder $query) => $query->where('user_id', $lecturer->id));
    }

    public function courseCards(User $lecturer): Builder
    {
        return $this->courses($lecturer)
            ->withCount([
                'enrollments as enrolled_students_count' => fn (Builder $query) => $query->where('status', 'enrolled'),
                'materials as visible_materials_count' => fn (Builder $query) => $query->where('is_visible', true),
                'submissionTasks as published_assignments_count' => fn (Builder $query) => $query->where('status', 'published'),
                'submissions as pending_reviews_count' => fn (Builder $query) => $query->whereIn('status', ['submitted', 'under_review', 'correction_requested']),
            ]);
    }

    /** @return array<string,mixed> */
    public function overview(User $lecturer, bool $includeAiDrafts = true): array
    {
        $courseIds = $this->courses($lecturer)->select('courses.id');
        $assignedCourses = $this->courses($lecturer)->count();
        $pendingReviews = Submission::query()->whereIn('course_id', $courseIds)
            ->whereIn('status', ['submitted', 'under_review', 'correction_requested'])->count();
        $openQuestions = Discussion::query()->whereIn('course_id', $courseIds)
            ->where('status', 'open')->where('user_id', '!=', $lecturer->id)->count();
        $draftCounts = $includeAiDrafts
            ? AiContentDraft::query()->where('user_id', $lecturer->id)
                ->whereIn('course_id', $courseIds)->whereIn('status', [AiContentDraft::STATUS_PENDING, AiContentDraft::STATUS_GENERATING, AiContentDraft::STATUS_READY])
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $coursesWithoutMaterial = $this->courses($lecturer)
            ->whereDoesntHave('materials', fn (Builder $query) => $query->where('is_visible', true))->count();

        $review = $pendingReviews > 0 ? Submission::query()->whereIn('course_id', $courseIds)
            ->whereIn('status', ['submitted', 'under_review', 'correction_requested'])
            ->select(['id', 'uuid', 'course_id', 'user_id', 'title', 'status', 'submitted_at'])
            ->with('course:id,uuid,code,name', 'user:id,first_name,last_name')->oldest('submitted_at')->oldest('id')->first() : null;
        $questions = $openQuestions > 0 ? Discussion::query()->whereIn('course_id', $courseIds)
            ->where('status', 'open')->where('user_id', '!=', $lecturer->id)
            ->select(['id', 'uuid', 'course_id', 'user_id', 'title', 'created_at'])
            ->with('course:id,uuid,code,name')->oldest('created_at')->oldest('id')->limit(4)->get() : collect();
        $question = $questions->first();
        $courseToPrepare = ! $review && ! $question ? $this->courseCards($lecturer)
            ->orderBy('code')->orderBy('id')->first() : null;

        return [
            'assignedCourses' => $assignedCourses,
            'pendingReviews' => $pendingReviews,
            'openQuestions' => $openQuestions,
            'readyDrafts' => (int) ($draftCounts[AiContentDraft::STATUS_READY] ?? 0),
            'draftsInProgress' => (int) (($draftCounts[AiContentDraft::STATUS_PENDING] ?? 0) + ($draftCounts[AiContentDraft::STATUS_GENERATING] ?? 0)),
            'coursesWithoutMaterial' => $coursesWithoutMaterial,
            'nextReview' => $review,
            'nextQuestion' => $question,
            'questions' => $questions,
            'courseToPrepare' => $courseToPrepare,
        ];
    }

    /** @param Collection<int,Course> $courses */
    public function dashboardSignals(User $lecturer, Collection $courses, bool $includeAiDrafts = true): array
    {
        $courseIds = $this->courses($lecturer)->select('courses.id');
        $openQuestions = Discussion::query()->whereIn('course_id', $courseIds)
            ->where('status', 'open')->where('user_id', '!=', $lecturer->id)->count();
        $draftCounts = $includeAiDrafts
            ? AiContentDraft::query()->where('user_id', $lecturer->id)
                ->whereIn('course_id', $courseIds)->whereIn('status', [AiContentDraft::STATUS_PENDING, AiContentDraft::STATUS_GENERATING, AiContentDraft::STATUS_READY])
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')
            : collect();

        return [
            'openQuestions' => $openQuestions,
            'readyDrafts' => (int) ($draftCounts[AiContentDraft::STATUS_READY] ?? 0),
            'draftsInProgress' => (int) (($draftCounts[AiContentDraft::STATUS_PENDING] ?? 0) + ($draftCounts[AiContentDraft::STATUS_GENERATING] ?? 0)),
            'coursesWithoutMaterial' => $courses->where('visible_materials_count', 0)->count(),
        ];
    }
}
