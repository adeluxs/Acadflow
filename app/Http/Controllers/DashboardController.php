<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicChallengeEntry;
use App\Models\AcademicEventRegistration;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GroupMember;
use App\Models\Invoice;
use App\Models\KnowledgeCommunityMember;
use App\Models\KnowledgePublication;
use App\Models\Notification;
use App\Models\ResearchProject;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\User;
use App\Services\AcademicPerformanceService;
use App\Services\FeatureAccessService;
use App\Services\Lecturer\TeachingWorkspaceService;
use App\Services\Student\StudyWorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function student()
    {
        $user = Auth::user();

        $enrollmentQuery = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled');
        $enrollmentCount = (clone $enrollmentQuery)->count();
        $enrollments = (clone $enrollmentQuery)
            ->with('course:id,uuid,code,name,access_mode')
            ->latest('enrolled_at')->latest('id')->limit(5)->get();

        // Full academic totals stay in SQL; hydrate only the five visible cards.
        $courseIds = (clone $enrollmentQuery)->select('course_id');
        $previewCourseIds = $enrollments->pluck('course_id');
        $submissions = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->select(['id', 'uuid', 'user_id', 'course_id', 'title', 'status', 'submitted_at', 'created_at'])
            ->with('course:id,uuid,code,name')
            ->latest()
            ->limit(5)
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
            ->with('course:id,uuid,code,name')
            ->orderByRaw('COALESCE(close_at, due_date) ASC')
            ->limit(5)
            ->get();

        $allTaskCounts = SubmissionTask::query()->whereIn('course_id', $previewCourseIds)->where('status', 'published')
            ->selectRaw('course_id, COUNT(*) as total')->groupBy('course_id')->pluck('total', 'course_id');
        $doneTaskCounts = Submission::query()->where('user_id', $user->id)->whereIn('course_id', $previewCourseIds)
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
        $announcements = Notification::query()
            ->where('user_id', $user->id)
            ->select(['id', 'user_id', 'title', 'message', 'created_at'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $studyToolsAccessible = $user->hasFeature('student_study_tools');
        $studyToday = $studyToolsAccessible && FeatureAccessService::canAccessFeature('courses', $user)
            ? app(StudyWorkspaceService::class)->today($user)
            : null;

        return view('dashboard.student', compact(
            'enrollments', 'enrollmentCount', 'submissions', 'submissionCount', 'pendingTasks', 'courseProgress',
            'cgpa', 'announcements', 'studyToday', 'studyToolsAccessible'
        ));
    }

    public function lecturer()
    {
        $user = Auth::user();

        $courseQuery = Course::query()
            ->whereHas('lecturerAssignments', fn (Builder $query) => $query->where('user_id', $user->id));
        $courseCount = (clone $courseQuery)->count();
        $courses = (clone $courseQuery)
            ->with(['department'])
            ->withCount([
                'enrollments as enrolled_students_count' => fn (Builder $query) => $query->where('status', 'enrolled'),
                'submissionTasks as assignment_count',
                'materials as visible_materials_count' => fn (Builder $query) => $query->where('is_visible', true),
            ])
            ->orderBy('code')->orderBy('id')->limit(6)
            ->get();

        $courseIds = (clone $courseQuery)->select('courses.id');
        $studentCount = Enrollment::query()->whereIn('course_id', $courseIds)->where('status', 'enrolled')->distinct('user_id')->count('user_id');

        $recentSubmissions = Submission::query()->whereIn('course_id', $courseIds)
            ->with(['user', 'course', 'grade'])->latest('submitted_at')->limit(8)->get();

        $submissionOverviewRaw = Submission::query()->whereIn('course_id', $courseIds)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pendingReviews = (int) ($submissionOverviewRaw['submitted'] ?? 0) + (int) ($submissionOverviewRaw['under_review'] ?? 0) + (int) ($submissionOverviewRaw['correction_requested'] ?? 0);
        $submissionOverview = [
            'pending' => (int) (($submissionOverviewRaw['submitted'] ?? 0) + ($submissionOverviewRaw['draft'] ?? 0)),
            'in_review' => (int) ($submissionOverviewRaw['under_review'] ?? 0),
            'returned' => (int) (($submissionOverviewRaw['correction_requested'] ?? 0) + ($submissionOverviewRaw['rejected'] ?? 0)),
            'approved' => (int) (($submissionOverviewRaw['approved'] ?? 0) + ($submissionOverviewRaw['graded'] ?? 0)),
        ];
        $submissionOverview['total'] = array_sum($submissionOverview);

        $performance = app(AcademicPerformanceService::class)->coursePerformanceSummary($courses->pluck('id'));
        $coursePerformance = $courses->map(fn ($course) => [
            'course' => $course,
            'average' => $performance['courses'][$course->id] ?? null,
        ]);
        $averageClass = app(AcademicPerformanceService::class)->averagePercentage($courseIds);

        $upcomingTasks = SubmissionTask::query()->whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'draft'])
            ->where(function (Builder $query): void {
                $query->where('close_at', '>=', now())->orWhere('due_date', '>=', now());
            })
            ->with('course')->orderByRaw('COALESCE(close_at, due_date) ASC')->limit(5)->get();

        $activeSessions = FeatureAccessService::canAccessFeature('attendance', $user)
            ? AttendanceSession::query()->where('lecturer_id', $user->id)->whereIn('course_id', $courseIds)
                ->where('status', 'active')->with(['course'])->get()
            : collect();

        $teachingToolsAccessible = $user->hasFeature('lecturer_teaching_tools');
        $teachingSignals = $teachingToolsAccessible
            ? app(TeachingWorkspaceService::class)->dashboardSignals(
                $user,
                $courses,
                FeatureAccessService::canAccessFeature('ai_assistant', $user),
            )
            : ['openQuestions' => 0, 'readyDrafts' => 0, 'draftsInProgress' => 0, 'coursesWithoutMaterial' => 0];

        return view('dashboard.lecturer', compact(
            'courses', 'courseCount', 'studentCount', 'pendingReviews', 'recentSubmissions', 'submissionOverview',
            'coursePerformance', 'averageClass', 'upcomingTasks', 'activeSessions', 'teachingSignals', 'teachingToolsAccessible'
        ));
    }

    public function admin()
    {
        $user = Auth::user();

        $users = $this->scopeUsers(User::query(), $user);
        $courses = $this->scopeCourses(Course::query(), $user);
        $submissions = $this->scopeSubmissions(Submission::query(), $user);
        $invoices = $this->scopeInvoices(Invoice::query(), $user);

        $userCounts = (clone $users)
            ->selectRaw("SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS students")
            ->selectRaw("SUM(CASE WHEN role = 'lecturer' THEN 1 ELSE 0 END) AS lecturers")
            ->first();

        $stats = [
            'total_students' => (int) ($userCounts?->students ?? 0),
            'total_lecturers' => (int) ($userCounts?->lecturers ?? 0),
            'total_courses' => $courses->count(),
            'total_submissions' => $submissions->count(),
            'pending_payments' => $invoices->where('status', 'pending')->count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }

    public function member()
    {
        $user = Auth::user();
        $communitiesEnabled = FeatureAccessService::canAccessFeature('knowledge_communities', $user);
        $groupsEnabled = FeatureAccessService::canAccessFeature('group_collaboration', $user);
        $eventsEnabled = FeatureAccessService::canAccessFeature('academic_events', $user);
        $challengesEnabled = FeatureAccessService::canAccessFeature('academic_challenges', $user);

        $stats = [
            'publications' => KnowledgePublication::query()->where('creator_id', $user->id)->count(),
            'research_projects' => ResearchProject::query()->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhere('supervisor_id', $user->id)
                    ->orWhere('co_supervisor_id', $user->id)
                    ->orWhereHas('memberRecords', fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id));
            })->count(),
            'communities' => $communitiesEnabled
                ? KnowledgeCommunityMember::query()->where('user_id', $user->id)->where('status', 'active')->count()
                : 0,
            'groups' => $groupsEnabled
                ? GroupMember::query()->where('user_id', $user->id)->where('status', 'active')->count()
                : 0,
        ];

        $publications = KnowledgePublication::query()
            ->where('creator_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $eventRegistrations = $eventsEnabled
            ? AcademicEventRegistration::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])
                ->with('event')
                ->latest('registered_at')
                ->limit(5)
                ->get()
            : collect();

        $challengeEntries = $challengesEnabled
            ? AcademicChallengeEntry::query()
                ->where('user_id', $user->id)
                ->with('challenge')
                ->latest()
                ->limit(5)
                ->get()
            : collect();

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
        if ($user->isDepartmentAdmin()) {
            return $query->where('university_id', $user->university_id)->where('department_id', $user->department_id);
        }
        if ($user->isUniversityAdmin()) {
            return $query->where('university_id', $user->university_id);
        }

        return $query;
    }

    private function scopeCourses(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) {
            return $query->where('department_id', $user->department_id);
        }
        if ($user->isUniversityAdmin()) {
            return $query->whereHas('department.faculty', fn (Builder $q) => $q->where('university_id', $user->university_id));
        }

        return $query;
    }

    private function scopeSubmissions(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) {
            return $query->whereHas('course', fn (Builder $q) => $q->where('department_id', $user->department_id));
        }
        if ($user->isUniversityAdmin()) {
            return $query->whereHas('course.department.faculty', fn (Builder $q) => $q->where('university_id', $user->university_id));
        }

        return $query;
    }

    private function scopeInvoices(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) {
            return $query->whereHas('user', fn (Builder $q) => $q->where('university_id', $user->university_id)->where('department_id', $user->department_id));
        }
        if ($user->isUniversityAdmin()) {
            return $query->whereHas('user', fn (Builder $q) => $q->where('university_id', $user->university_id));
        }

        return $query;
    }
}
