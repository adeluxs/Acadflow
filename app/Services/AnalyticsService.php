<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\User;

class AnalyticsService
{
    public function getSubmissionStats($startDate = null, $endDate = null): array
    {
        $base = Submission::query();
        if ($startDate) {
            $base->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $base->where('created_at', '<=', $endDate);
        }

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'submitted' => (clone $base)->where('status', 'submitted')->count(),
            'under_review' => (clone $base)->where('status', 'under_review')->count(),
            'correction_requested' => (clone $base)->where('status', 'correction_requested')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'graded' => (clone $base)->where('status', 'graded')->count(),
        ];
    }

    public function getAttendanceStats($courseId = null, $startDate = null, $endDate = null): array
    {
        $base = AttendanceRecord::query();
        if ($courseId) {
            $base->whereHas('session', fn ($q) => $q->where('course_id', $courseId));
        }
        if ($startDate) {
            $base->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $base->where('created_at', '<=', $endDate);
        }

        $total = (clone $base)->count();
        $presentOrLate = (clone $base)->whereIn('status', ['present', 'late'])->count();

        return [
            'total' => $total,
            'present' => (clone $base)->where('status', 'present')->count(),
            'late' => (clone $base)->where('status', 'late')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'invalid' => (clone $base)->where('status', 'invalid')->count(),
            'attendance_rate' => $total > 0 ? round(($presentOrLate / $total) * 100, 2) : 0.0,
        ];
    }

    public function getBillingStats($universityId = null, $semesterId = null): array
    {
        $base = Invoice::query();
        if ($universityId) {
            $base->whereHas('user', fn ($q) => $q->where('university_id', $universityId));
        }
        if ($semesterId) {
            $base->where('semester_id', $semesterId);
        }

        $total = (clone $base)->count();
        $paid = (clone $base)->where('status', 'paid')->count();

        return [
            'total_invoices' => $total,
            'total_amount' => (clone $base)->sum('amount'),
            'paid' => $paid,
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'overdue' => (clone $base)->where('status', 'overdue')->count(),
            'waived' => (clone $base)->where('status', 'waived')->count(),
            'collection_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0.0,
        ];
    }

    public function getCoursePerformance($departmentId = null): array
    {
        $query = Course::query()->withCount(['enrollments', 'submissions']);
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->get()->map(function (Course $course) {
            $average = $course->submissions()
                ->whereHas('grade')
                ->with('grade:id,submission_id,score')
                ->get()
                ->pluck('grade.score')
                ->filter(fn ($score) => $score !== null)
                ->avg();

            return [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'enrolled' => $course->enrollments_count,
                'submissions' => $course->submissions_count,
                'graded' => $course->submissions()->whereIn('status', ['graded', 'approved'])->count(),
                'average_score' => round((float) ($average ?? 0), 2),
            ];
        })->all();
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
        if ($startDate) {
            $base->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $base->where('created_at', '<=', $endDate);
        }

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        $late = (clone $base)->whereNotNull('submitted_at')->whereColumn('submitted_at', '>', 'due_date')->count();

        return round(($late / $total) * 100, 2);
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
