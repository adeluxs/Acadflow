<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubmissionGrade;
use App\Models\User;
use Illuminate\Support\Collection;

class AcademicPerformanceService
{
    public function studentCgpa(User $student, Collection|array $courseIds): ?float
    {
        $courseIds = collect($courseIds)->filter()->values();
        if ($courseIds->isEmpty()) return null;

        $grades = SubmissionGrade::query()
            ->whereHas('submission', fn ($query) => $query
                ->where('user_id', $student->id)
                ->whereIn('course_id', $courseIds))
            ->where('is_final', true)
            ->with('submission.course:id,credit_hours')
            ->get()
            ->filter(fn ($grade) => (float) $grade->max_score > 0 && $grade->submission?->course);

        if ($grades->isEmpty()) return null;

        $perCourse = $grades->groupBy(fn ($grade) => $grade->submission->course_id);
        $weightedPoints = 0.0;
        $credits = 0;

        foreach ($perCourse as $courseGrades) {
            $course = $courseGrades->first()->submission->course;
            $percent = $courseGrades->avg(fn ($grade) => ((float) $grade->score / (float) $grade->max_score) * 100);
            $credit = max(1, (int) $course->credit_hours);
            $weightedPoints += $this->gradePoint($percent) * $credit;
            $credits += $credit;
        }

        return $credits > 0 ? round($weightedPoints / $credits, 2) : null;
    }

    public function averagePercentage(Collection|array $courseIds): ?float
    {
        $ids = collect($courseIds)->filter()->values();
        if ($ids->isEmpty()) return null;

        $grades = SubmissionGrade::query()
            ->whereHas('submission', fn ($query) => $query->whereIn('course_id', $ids))
            ->where('is_final', true)
            ->get()
            ->filter(fn ($grade) => (float) $grade->max_score > 0);

        if ($grades->isEmpty()) return null;
        return round((float) $grades->avg(fn ($grade) => ((float) $grade->score / (float) $grade->max_score) * 100), 1);
    }

    private function gradePoint(float $percent): float
    {
        $raw = SettingService::get('gpa_grade_bands', [
            ['min' => 70, 'point' => 5],
            ['min' => 60, 'point' => 4],
            ['min' => 50, 'point' => 3],
            ['min' => 45, 'point' => 2],
            ['min' => 40, 'point' => 1],
            ['min' => 0, 'point' => 0],
        ]);
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        foreach ((array) $raw as $band) {
            if ($percent >= (float) ($band['min'] ?? 0)) return (float) ($band['point'] ?? 0);
        }
        return 0.0;
    }
}
