<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\ResearchProject;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeCommunityMember;
use App\Models\AcademicEventRegistration;
use App\Models\AcademicChallengeEntry;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Models\SubmissionTask;
use App\Models\Notification;
use App\Services\AcademicPerformanceService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function student()
    {
        $user = Auth::user();

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->whereHas('course.department.faculty', fn (Builder $query) => $query->where('university_id', $user->university_id))
            ->with(['course.department', 'semester'])
            ->latest('enrolled_at')
            ->get();

        $courseIds = $enrollments->pluck('course_id');
        $submissions = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->with(['course', 'task', 'grade'])
            ->latest()
            ->limit(8)
            ->get();

        $submissionCount = Submission::query()->where('user_id', $user->id)->whereIn('course_id', $courseIds)->count();

        $pendingTasks = SubmissionTask::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
            ->where(function (Builder $query): void {
                $query->whereNull('close_at')->orWhere('close_at', '>=', now());
            })
            ->whereDoesntHave('submissions', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with('course')
            ->orderByRaw('COALESCE(close_at, due_date) ASC')
            ->limit(8)
            ->get();

        $allTaskCounts = SubmissionTask::query()->whereIn('course_id', $courseIds)->where('status', 'published')
            ->selectRaw('course_id, COUNT(*) as total')->groupBy('course_id')->pluck('total', 'course_id');
        $doneTaskCounts = Submission::query()->where('user_id', $user->id)->whereIn('course_id', $courseIds)
            ->whereNotNull('submission_task_id')->selectRaw('course_id, COUNT(DISTINCT submission_task_id) as total')
            ->groupBy('course_id')->pluck('total', 'course_id');

        $courseProgress = $enrollments->map(function ($enrollment) use ($allTaskCounts, $doneTaskCounts) {
            $total = (int) ($allTaskCounts[$enrollment->course_id] ?? 0);
            $done = (int) ($doneTaskCounts[$enrollment->course_id] ?? 0);
            return [
                'course' => $enrollment->course,
                'progress' => $total > 0 ? min(100, (int) round(($done / $total) * 100)) : 0,
                'completed' => $done,
                'total' => $total,
            ];
        });

        $cgpa = app(AcademicPerformanceService::class)->studentCgpa($user, $courseIds);
        $pendingInvoices = Invoice::query()->where('user_id', $user->id)->where('status', 'pending')->get();
        $groups = Group::query()->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('course.department.faculty', fn (Builder $query) => $query->where('university_id', $user->university_id))
            ->with(['course', 'members'])->get();
        $announcements = Notification::query()->where('user_id', $user->id)->latest('created_at')->limit(5)->get();

        return view('dashboard.student', compact(
            'enrollments', 'submissions', 'submissionCount', 'pendingTasks', 'courseProgress',
            'cgpa', 'pendingInvoices', 'groups', 'announcements'
        ));
    }

    public function lecturer()
    {
        $user = Auth::user();

        $courses = Course::query()
            ->whereHas('lecturerAssignments', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('department.faculty', fn (Builder $query) => $query->where('university_id', $user->university_id))
            ->with(['department'])
            ->withCount([
                'enrollments as enrolled_students_count' => fn (Builder $query) => $query->where('status', 'enrolled'),
                'submissionTasks as assignment_count',
            ])
            ->orderBy('code')
            ->get();

        $courseIds = $courses->pluck('id');
        $studentCount = Enrollment::query()->whereIn('course_id', $courseIds)->where('status', 'enrolled')->distinct('user_id')->count('user_id');

        $pendingReviews = Submission::query()->whereIn('course_id', $courseIds)
            ->whereIn('status', ['submitted', 'under_review', 'correction_requested'])
            ->count();

        $recentSubmissions = Submission::query()->whereIn('course_id', $courseIds)
            ->with(['user', 'course', 'grade'])->latest('submitted_at')->limit(8)->get();

        $submissionOverviewRaw = Submission::query()->whereIn('course_id', $courseIds)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $submissionOverview = [
            'pending' => (int) (($submissionOverviewRaw['submitted'] ?? 0) + ($submissionOverviewRaw['draft'] ?? 0)),
            'in_review' => (int) ($submissionOverviewRaw['under_review'] ?? 0),
            'returned' => (int) (($submissionOverviewRaw['correction_requested'] ?? 0) + ($submissionOverviewRaw['rejected'] ?? 0)),
            'approved' => (int) (($submissionOverviewRaw['approved'] ?? 0) + ($submissionOverviewRaw['graded'] ?? 0)),
        ];
        $submissionOverview['total'] = array_sum($submissionOverview);

        $gradeRows = SubmissionGrade::query()->where('is_final', true)
            ->whereHas('submission', fn (Builder $query) => $query->whereIn('course_id', $courseIds))
            ->with('submission:id,course_id')->get()->filter(fn ($grade) => (float) $grade->max_score > 0);
        $coursePerformance = $courses->map(function ($course) use ($gradeRows) {
            $grades = $gradeRows->filter(fn ($grade) => $grade->submission?->course_id === $course->id);
            $average = $grades->isEmpty() ? null : round((float) $grades->avg(fn ($grade) => ((float) $grade->score / (float) $grade->max_score) * 100), 1);
            return ['course' => $course, 'average' => $average];
        });
        $averageClass = app(AcademicPerformanceService::class)->averagePercentage($courseIds);

        $upcomingTasks = SubmissionTask::query()->whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'draft'])
            ->where(function (Builder $query): void { $query->where('close_at', '>=', now())->orWhere('due_date', '>=', now()); })
            ->with('course')->orderByRaw('COALESCE(close_at, due_date) ASC')->limit(5)->get();

        $activeSessions = AttendanceSession::query()->where('lecturer_id', $user->id)->whereIn('course_id', $courseIds)
            ->where('status', 'active')->with(['course'])->get();

        return view('dashboard.lecturer', compact(
            'courses', 'studentCount', 'pendingReviews', 'recentSubmissions', 'submissionOverview',
            'coursePerformance', 'averageClass', 'upcomingTasks', 'activeSessions'
        ));
    }

    public function admin()
    {
        $user = Auth::user();

        $users = $this->scopeUsers(User::query(), $user);
        $courses = $this->scopeCourses(Course::query(), $user);
        $submissions = $this->scopeSubmissions(Submission::query(), $user);
        $invoices = $this->scopeInvoices(Invoice::query(), $user);

        $stats = [
            'total_students' => (clone $users)->where('role', 'student')->count(),
            'total_lecturers' => (clone $users)->where('role', 'lecturer')->count(),
            'total_courses' => $courses->count(),
            'total_submissions' => $submissions->count(),
            'pending_payments' => $invoices->where('status', 'pending')->count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }


    public function member()
    {
        $user = Auth::user();

        $stats = [
            'publications' => KnowledgePublication::query()->where('creator_id', $user->id)->count(),
            'research_projects' => ResearchProject::query()->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('memberRecords', fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id));
            })->count(),
            'communities' => KnowledgeCommunityMember::query()->where('user_id', $user->id)->where('status', 'active')->count(),
            'groups' => GroupMember::query()->where('user_id', $user->id)->where('status', 'active')->count(),
        ];

        $publications = KnowledgePublication::query()
            ->where('creator_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $eventRegistrations = AcademicEventRegistration::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])
            ->with('event')
            ->latest('registered_at')
            ->limit(5)
            ->get();

        $challengeEntries = AcademicChallengeEntry::query()
            ->where('user_id', $user->id)
            ->with('challenge')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.member', compact('stats', 'publications', 'eventRegistrations', 'challengeEntries'));
    }

    public function index()
    {
        $user = Auth::user();

        return match ($user->role) {
            'super_admin', 'university_admin', 'department_admin' => $this->admin(),
            'lecturer' => $this->lecturer(),
            'student' => $this->student(),
            'member' => $this->member(),
            default => $this->member(),
        };
    }

    private function scopeUsers(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->where('university_id', $user->university_id)->where('department_id', $user->department_id);
        if ($user->isUniversityAdmin()) return $query->where('university_id', $user->university_id);
        return $query;
    }

    private function scopeCourses(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->where('department_id', $user->department_id);
        if ($user->isUniversityAdmin()) return $query->whereHas('department.faculty', fn (Builder $q) => $q->where('university_id', $user->university_id));
        return $query;
    }

    private function scopeSubmissions(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->whereHas('course', fn (Builder $q) => $q->where('department_id', $user->department_id));
        if ($user->isUniversityAdmin()) return $query->whereHas('course.department.faculty', fn (Builder $q) => $q->where('university_id', $user->university_id));
        return $query;
    }

    private function scopeInvoices(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->whereHas('user', fn (Builder $q) => $q->where('university_id', $user->university_id)->where('department_id', $user->department_id));
        if ($user->isUniversityAdmin()) return $query->whereHas('user', fn (Builder $q) => $q->where('university_id', $user->university_id));
        return $query;
    }
}
