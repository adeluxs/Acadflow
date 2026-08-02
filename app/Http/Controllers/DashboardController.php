<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function student()
    {
        $user = Auth::user();

        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->with(['course', 'semester'])
            ->get();

        $submissions = Submission::where('user_id', $user->id)
            ->with(['course'])
            ->latest()
            ->limit(5)
            ->get();

        $pendingInvoices = Invoice::where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();

        $groups = Group::whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['course', 'members'])
            ->get();

        return view('dashboard.student', compact('enrollments', 'submissions', 'pendingInvoices', 'groups'));
    }

    public function lecturer()
    {
        $user = Auth::user();

        $courses = Course::whereHas('lecturerAssignments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['department'])->get();

        $pendingReviews = Submission::whereIn('course_id', $courses->pluck('id'))
            ->whereIn('status', ['submitted', 'under_review'])
            ->with(['user', 'course'])
            ->count();

        $activeSessions = AttendanceSession::where('lecturer_id', $user->id)
            ->where('status', 'active')
            ->with(['course'])
            ->get();

        return view('dashboard.lecturer', compact('courses', 'pendingReviews', 'activeSessions'));
    }

    public function admin()
    {
        $user = Auth::user();

        $stats = [
            'total_students' => User::where('role', 'student')->count(),
            'total_lecturers' => User::where('role', 'lecturer')->count(),
            'total_courses' => Course::count(),
            'total_submissions' => Submission::count(),
            'pending_payments' => Invoice::where('status', 'pending')->count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }

    public function index()
    {
        $user = Auth::user();

        return match ($user->role) {
            'super_admin', 'university_admin', 'department_admin' => $this->admin(),
            'lecturer' => $this->lecturer(),
            'student' => $this->student(),
            default => redirect()->route('login'),
        };
    }
}
