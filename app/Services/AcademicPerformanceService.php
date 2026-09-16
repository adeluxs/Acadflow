<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicPerformanceService
{
    public function studentCgpa(User $student, Collection|array $courseIds): ?float
    {
        $courseIds = collect($courseIds)->filter()->values();
        if ($courseIds->isEmpty()) return null;

        $perCourse = DB::table('submission_grades')
            ->join('submissions', 'submissions.id', '=', 'submission_grades.submission_id')
            ->join('courses', 'courses.id', '=', 'submissions.course_id')
            ->where('submissions.user_id', $student->id)
            ->whereIn('submissions.course_id', $courseIds)
            ->where('submission_grades.is_final', true)
            ->where('submission_grades.max_score', '>', 0)
            ->selectRaw('submissions.course_id, courses.credit_hours, AVG((submission_grades.score * 100.0) / NULLIF(submission_grades.max_score, 0)) as average_percentage')
            ->groupBy('submissions.course_id', 'courses.credit_hours')
            ->get();

        if ($perCourse->isEmpty()) return null;

        $bands = $this->gradeBands();
        $weightedPoints = 0.0;
        $credits = 0;

        foreach ($perCourse as $row) {
            $percent = (float) $row->average_percentage;
            $credit = max(1, (int) $row->credit_hours);
            $weightedPoints += $this->gradePoint($percent, $bands) * $credit;
            $credits += $credit;
        }

        return $credits > 0 ? round($weightedPoints / $credits, 2) : null;
    }

    public function averagePercentage(Collection|array $courseIds): ?float
    {
        $ids = collect($courseIds)->filter()->values();
        if ($ids->isEmpty()) return null;

        $average = DB::table('submission_grades')
            ->join('submissions', 'submissions.id', '=', 'submission_grades.submission_id')
            ->whereIn('submissions.course_id', $ids)
            ->where('submission_grades.is_final', true)
            ->where('submission_grades.max_score', '>', 0)
            ->avg(DB::raw('(submission_grades.score * 100.0) / NULLIF(submission_grades.max_score, 0)'));

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * Return per-course and overall percentages in one aggregate query.
     *
     * @return array{courses:array<int,float>,overall:?float}
     */
    public function coursePerformanceSummary(Collection|array $courseIds): array
    {
        $ids = collect($courseIds)->filter()->values();
        if ($ids->isEmpty()) return ['courses' => [], 'overall' => null];

        $rows = DB::table('submission_grades')
            ->join('submissions', 'submissions.id', '=', 'submission_grades.submission_id')
            ->whereIn('submissions.course_id', $ids)
            ->where('submission_grades.is_final', true)
            ->where('submission_grades.max_score', '>', 0)
            ->selectRaw('submissions.course_id, AVG((submission_grades.score * 100.0) / NULLIF(submission_grades.max_score, 0)) as average_percentage, SUM((submission_grades.score * 100.0) / NULLIF(submission_grades.max_score, 0)) as percentage_sum, COUNT(*) as grade_count')
            ->groupBy('submissions.course_id')
            ->get();

        $courses = [];
        $sum = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $courses[(int) $row->course_id] = round((float) $row->average_percentage, 1);
            $sum += (float) $row->percentage_sum;
            $count += (int) $row->grade_count;
        }

        return [
            'courses' => $courses,
            'overall' => $count > 0 ? round($sum / $count, 1) : null,
        ];
    }

    private function gradeBands(): array
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
        return (array) $raw;
    }

    private function gradePoint(float $percent, array $bands): float
    {
        foreach ($bands as $band) {
            if ($percent >= (float) ($band['min'] ?? 0)) return (float) ($band['point'] ?? 0);
        }
        return 0.0;
    }
}
