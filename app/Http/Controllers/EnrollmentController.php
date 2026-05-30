<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Semester;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    // Get student's enrollments
    public function index()
    {
        $enrollments = Enrollment::where('user_id', Auth::id())
            ->where('status', 'enrolled')
            ->with(['course', 'semester'])
            ->latest()
            ->paginate(10);

        return view('enrollments.index', compact('enrollments'));
    }

    // Enroll in a course
    public function enroll(Request $request, Course $course)
    {
        $semester = Semester::where('is_active', true)->firstOrFail();

        $existing = Enrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existing) {
            return back()->with('error', 'Already enrolled in this course.');
        }

        // Check if user has paid for semester
        $invoice = Invoice::where('user_id', Auth::id())
            ->where('semester_id', $semester->id)
            ->where('status', 'paid')
            ->first();

        // Allow enrollment if paid or if within grace period
        $subscription = Subscription::where('university_id', Auth::user()->university_id)
            ->where('is_active', true)
            ->first();

        $graceDays = $subscription?->grace_days ?? 7;
        $withinGrace = now()->lessThanOrEqualTo($semester->start_date->addDays($graceDays));

        if (! $invoice && ! $withinGrace) {
            return redirect()->route('billing.my')
                ->with('error', 'Please pay your semester fees to enroll in courses.');
        }

        // Check capacity
        if ($course->max_capacity) {
            $count = Enrollment::where('course_id', $course->id)
                ->where('status', 'enrolled')
                ->count();
            if ($count >= $course->max_capacity) {
                return back()->with('error', 'Course is at maximum capacity.');
            }
        }

        Enrollment::create([
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return redirect()->route('enrollments.index')
            ->with('success', 'Successfully enrolled in '.$course->name);
    }

    // Drop a course
    public function drop(Enrollment $enrollment)
    {
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        if ($enrollment->status !== 'enrolled') {
            return back()->with('error', 'Cannot drop this enrollment.');
        }

        $enrollment->update(['status' => 'dropped']);

        return redirect()->route('enrollments.index')
            ->with('success', 'Course dropped successfully.');
    }

    // Admin: View all enrollments for a course
    public function forCourse(Course $course)
    {
        $user = Auth::user();

        // Check scope
        if ($user->isDepartmentAdmin() && $course->department_id !== $user->department_id) {
            abort(403, 'Access denied.');
        }
        if ($user->isUniversityAdmin() && $course->department->faculty->university_id !== $user->university_id) {
            abort(403, 'Access denied.');
        }

        $enrollments = Enrollment::where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->with('user')
            ->get();

        return view('enrollments.course', compact('enrollments', 'course'));
    }
}
