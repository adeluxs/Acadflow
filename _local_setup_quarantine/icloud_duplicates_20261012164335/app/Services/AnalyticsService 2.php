<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getSubmissionStats($startDate = null, $endDate = null): array
    {
        $base = Submission::query();
        if ($startDate) $base->where('created_at', '>=', $startDate);
        if ($endDate) $base->where('created_at', '<=', $endDate);

        $row = $base
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft")
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS submitted")
            ->selectRaw("SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) AS under_review")
            ->selectRaw("SUM(CASE WHEN status = 'correction_requested' THEN 1 ELSE 0 END) AS correction_requested")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved")
            ->selectRaw("SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) AS graded")
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'draft' => (int) ($row?->draft ?? 0),
            'submitted' => (int) ($row?->submitted ?? 0),
            'under_review' => (int) ($row?->under_review ?? 0),
            'correction_requested' => (int) ($row?->correction_requested ?? 0),
            'approved' => (int) ($row?->approved ?? 0),
            'graded' => (int) ($row?->graded ?? 0),
        ];
    }

    public function getAttendanceStats($courseId = null, $startDate = null, $endDate = null): array
    {
        $base = AttendanceRecord::query();
        if ($courseId) $base->whereHas('session', fn ($q) => $q->where('course_id', $courseId));
        if ($startDate) $base->where('created_at', '>=', $startDate);
        if ($endDate) $base->where('created_at', '<=', $endDate);

        $row = $base
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent")
            ->selectRaw("SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) AS invalid")
            ->first();

        $total = (int) ($row?->total ?? 0);
        $present = (int) ($row?->present ?? 0);
        $late = (int) ($row?->late ?? 0);

        return [
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => (int) ($row?->absent ?? 0),
            'invalid' => (int) ($row?->invalid ?? 0),
            'attendance_rate' => $total > 0 ? round((($present + $late) / $total) * 100, 2) : 0.0,
        ];
    }

    public function getBillingStats($universityId = null, $semesterId = null): array
    {
        $base = Invoice::query();
        if ($universityId) $base->whereHas('user', fn ($q) => $q->where('university_id', $universityId));
        if ($semesterId) $base->where('semester_id', $semesterId);

        $row = $base
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) AS overdue")
            ->selectRaw("SUM(CASE WHEN status = 'waived' THEN 1 ELSE 0 END) AS waived")
            ->first();

        $total = (int) ($row?->total ?? 0);
        $paid = (int) ($row?->paid ?? 0);

        return [
            'total_invoices' => $total,
            'total_amount' => (float) ($row?->total_amount ?? 0),
            'paid' => $paid,
            'pending' => (int) ($row?->pending ?? 0),
            'overdue' => (int) ($row?->overdue ?? 0),
            'waived' => (int) ($row?->waived ?? 0),
            'collection_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0.0,
        ];
    }

    public function getCoursePerformance($departmentId = null): array
    {
        $query = Course::query()->withCount([
            'enrollments',
            'submissions',
            'submissions as graded_submissions_count' => fn ($q) => $q->whereIn('status', ['graded', 'approved']),
        ]);
        if ($departmentId) $query->where('department_id', $departmentId);

        $courses = $query->get();
        if ($courses->isEmpty()) return [];

        // Submission::grade is a HasOne relation. Match its effective first-row
        // semantics in one grouped query instead of loading every submission+grade
        // for every course.
        $firstGradeIds = DB::table('submission_grades')
            ->selectRaw('submission_id, MIN(id) AS id')
            ->groupBy('submission_id');
        $averages = DB::table('submissions')
            ->joinSub($firstGradeIds, 'first_grade', fn ($join) => $join->on('first_grade.submission_id', '=', 'submissions.id'))
            ->join('submission_grades', function ($join): void {
                $join->on('submission_grades.id', '=', 'first_grade.id')
                    ->on('submission_grades.submission_id', '=', 'submissions.id');
            })
            ->whereIn('submissions.course_id', $courses->pluck('id'))
            ->groupBy('submissions.course_id')
            ->selectRaw('submissions.course_id, AVG(submission_grades.score) AS average_score')
            ->pluck('average_score', 'course_id');

        return $courses->map(fn (Course $course) => [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'enrolled' => $course->enrollments_count,
            'submissions' => $course->submissions_count,
            'graded' => $course->graded_submissions_count,
            'average_score' => round((float) ($averages[$course->id] ?? 0), 2),
        ])->all();
    }

    public function getDepartmentSummary($universityId = null): array
    {
        $query = Department::query()->withCount(['courses', 'users']);
        if ($universityId) {
            $query->whereHas('faculty', fn ($q) => $q->where('university_id', $universityId));
        }

        return $query->get()->map(fn (Department $department) => [
            'id' => $department->id,
            'name' => $department->name,
            'courses_count' => $department->courses_count,
            'users_count' => $department->users_count,
        ])->all();
    }

    public function getUserActivity($userId, $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'submissions' => Submission::where('user_id', $userId)->where('created_at', '>=', $startDate)->count(),
            'attendance' => AttendanceRecord::where('user_id', $userId)->where('created_at', '>=', $startDate)->count(),
            'last_active' => User::find($userId)?->last_login_at,
        ];
    }

    public function getLateSubmissionRate($startDate = null, $endDate = null): float
    {
        $base = Submission::query()->whereNotNull('due_date');
        if ($startDate) $base->where('created_at', '>=', $startDate);
        if ($endDate) $base->where('created_at', '<=', $endDate);

        $row = $base
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at > due_date THEN 1 ELSE 0 END) AS late')
            ->first();
        $total = (int) ($row?->total ?? 0);

        return $total > 0 ? round((((int) ($row?->late ?? 0)) / $total) * 100, 2) : 0.0;
    }

    public function exportToCsv($data, $filename): string
    {
        $handle = fopen('php://temp', 'w+');
        if (! empty($data)) {
            fputcsv($handle, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
