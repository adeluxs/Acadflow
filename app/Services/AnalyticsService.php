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
        $query = Submission::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return [
            'total' => $query->count(),
            'draft' => $query->where('status', 'draft')->count(),
            'submitted' => $query->where('status', 'submitted')->count(),
            'under_review' => $query->where('status', 'under_review')->count(),
            'correction_requested' => $query->where('status', 'correction_requested')->count(),
            'approved' => $query->where('status', 'approved')->count(),
            'graded' => $query->where('status', 'graded')->count(),
            'rejected' => $query->where('status', 'rejected')->count(),
        ];
    }

    public function getAttendanceStats($courseId = null, $startDate = null, $endDate = null): array
    {
        $query = AttendanceRecord::query();

        if ($courseId) {
            $query->whereHas('session', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count() ?: 1;

        return [
            'total' => $total,
            'present' => $query->where('status', 'present')->count(),
            'late' => $query->where('status', 'late')->count(),
            'absent' => $query->where('status', 'absent')->count(),
            'invalid' => $query->where('status', 'invalid')->count(),
            'attendance_rate' => round((($query->whereIn('status', ['present', 'late'])->count()) / $total) * 100, 2),
        ];
    }

    public function getBillingStats($universityId = null, $semesterId = null): array
    {
        $query = Invoice::query();

        if ($universityId) {
            $query->whereHas('user', function ($q) use ($universityId) {
                $q->where('university_id', $universityId);
            });
        }

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $total = $query->count() ?: 1;
        $paid = $query->where('status', 'paid')->count();

        return [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'paid' => $paid,
            'pending' => $query->where('status', 'pending')->count(),
            'overdue' => $query->where('status', 'overdue')->count(),
            'waived' => $query->where('status', 'waived')->count(),
            'collection_rate' => round(($paid / $total) * 100, 2),
        ];
    }

    public function getCoursePerformance($departmentId = null): array
    {
        $query = Course::with(['enrollments', 'submissions']);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $courses = $query->get();

        return $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'enrolled' => $course->enrollments->count(),
                'submissions' => $course->submissions->count(),
                'graded' => $course->submissions->where('status', 'graded')->count(),
                'average_score' => $course->submissions->grades->avg('score') ?? 0,
            ];
        })->toArray();
    }

    public function getDepartmentSummary($universityId = null): array
    {
        $query = Department::query();

        if ($universityId) {
            $query->where('university_id', $universityId);
        }

        return $query->withCount('courses', 'enrollments', 'users')->get()->toArray();
    }

    public function getUserActivity($userId, $days = 30): array
    {
        $startDate = now()->subDays($days);

        $submissions = Submission::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->count();

        $attendance = AttendanceRecord::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->count();

        return [
            'submissions' => $submissions,
            'attendance' => $attendance,
            'last_active' => User::find($userId)?->last_login_at,
        ];
    }

    public function getLateSubmissionRate($startDate = null, $endDate = null): float
    {
        $query = Submission::whereNotNull('due_date');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $late = $query->where(function ($q) {
            $q->whereColumn('submitted_at', '>', 'due_date')
                ->orWhereNull('submitted_at');
        })->count();

        return round(($late / $total) * 100, 2);
    }

    public function exportToCsv($data, $filename): string
    {
        $handle = fopen('php://temp', 'w');

        if (! empty($data)) {
            fputcsv($handle, array_keys($data[0]));

            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
