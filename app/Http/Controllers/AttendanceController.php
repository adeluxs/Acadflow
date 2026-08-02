<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\QrRefreshed;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    // Lecturer: Start session
    public function startSession(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        // Check if subscription allows attendance tracking
        $user = Auth::user();
        $subscription = $user->activeSubscription()->first();
        if ($subscription && $subscription->plan && ! $subscription->plan->allow_attendance_tracking) {
            return back()->with('error', 'Your subscription plan does not allow attendance tracking. Please upgrade your plan.');
        }

        $course = Course::findOrFail($validated['course_id']);
        $semester = Semester::where('is_active', true)->first();

        $session = AttendanceSession::create([
            'uuid' => Str::uuid(),
            'course_id' => $validated['course_id'],
            'semester_id' => $semester?->id,
            'lecturer_id' => Auth::id(),
            'qr_code' => Str::random(32),
            'qr_expires_at' => now()->addMinutes(1),
            'started_at' => now(),
            'status' => 'active',
        ]);

        // Create pending records for all enrolled students
        $enrollments = Enrollment::where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->get();

        foreach ($enrollments as $enrollment) {
            AttendanceRecord::create([
                'session_id' => $session->id,
                'user_id' => $enrollment->user_id,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('attendance.session', $session->uuid)
            ->with('success', 'Attendance session started.');
    }

    // Get active session for QR code (Student)
    public function activeSession()
    {
        $session = AttendanceSession::where('status', 'active')
            ->where('started_at', '>', now()->subMinutes(30))
            ->with('course')
            ->first();

        if (! $session) {
            return response()->json(['message' => 'No active session'], 404);
        }

        return response()->json([
            'uuid' => $session->uuid,
            'qr_code' => $session->qr_code,
            'qr_expires_at' => $session->qr_expires_at,
            'course' => $session->course->name,
        ]);
    }

    // Student: Check in
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'session_uuid' => 'required|exists:attendance_sessions,uuid',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
        ]);

        $session = AttendanceSession::where('uuid', $validated['session_uuid'])
            ->where('status', 'active')
            ->firstOrFail();

        if ($session->ended_at) {
            return back()->with('error', 'Session has ended.');
        }

        $record = AttendanceRecord::where('session_id', $session->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($record->status !== 'pending') {
            return back()->with('error', 'You have already checked in.');
        }

        $lateThreshold = $session->started_at->addMinutes($session->late_threshold);

        $status = now()->lessThanOrEqualTo($lateThreshold) ? 'present' : 'late';

        $record->update([
            'status' => $status,
            'check_in_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Check-in successful. Status: '.$status);
    }

    // View session details
    public function showSession(AttendanceSession $session)
    {
        $session->load(['records.user', 'course']);

        return view('attendance.session', compact('session'));
    }

    // Close session (Lecturer)
    public function closeSession(AttendanceSession $session)
    {
        if ($session->lecturer_id !== Auth::id()) {
            abort(403);
        }

        $session->update([
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        // Mark all pending as absent
        AttendanceRecord::where('session_id', $session->id)
            ->where('status', 'pending')
            ->update(['status' => 'absent']);

        return redirect()->route('dashboard')->with('success', 'Session closed.');
    }

    // Refresh QR code
    public function refreshQr(AttendanceSession $session)
    {
        if ($session->lecturer_id !== Auth::id()) {
            abort(403);
        }

        $session->update([
            'qr_code' => Str::random(32),
            'qr_expires_at' => now()->addMinutes(1),
        ]);

        event(new QrRefreshed($session, route('attendance.qr', $session), $session->qr_expires_at->toDateTimeString()));

        return response()->json([
            'qr_code' => $session->qr_code,
            'qr_expires_at' => $session->qr_expires_at,
        ]);
    }

    // Student: View my attendance
    public function myAttendance()
    {
        $records = AttendanceRecord::where('user_id', Auth::id())
            ->with(['session.course'])
            ->latest()
            ->paginate(10);

        return view('attendance.my', compact('records'));
    }

    // Student: View my attendance records (detailed)
    public function myAttendanceRecords(Request $request)
    {
        $query = AttendanceRecord::where('user_id', Auth::id())
            ->with(['session.course']);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->latest()->paginate(20);

        return view('attendance.records', compact('records'));
    }

    // Student: Export my attendance records
    public function exportMyAttendanceRecords(Request $request)
    {
        $query = AttendanceRecord::where('user_id', Auth::id())
            ->with(['session.course']);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        $records = $query->get();

        return response()->json($records);
    }

    // Lecturer: View all sessions
    public function lecturerSessions()
    {
        $user = Auth::user();
        $query = AttendanceSession::with(['course', 'records.user']);

        // Filter by scope based on role
        if ($user->isDepartmentAdmin()) {
            $query->whereHas('course', fn ($q) => $q->where('department_id', $user->department_id));
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('course.department.faculty', fn ($q) => $q->where('university_id', $user->university_id));
        } elseif ($user->isLecturer()) {
            $query->where('lecturer_id', $user->id);
        }

        $sessions = $query->latest()->paginate(10);

        // Get courses for session creation
        $coursesQuery = Course::with('department');
        if ($user->isLecturer()) {
            $coursesQuery->where('lecturer_id', $user->id);
        } elseif ($user->isDepartmentAdmin()) {
            $coursesQuery->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $coursesQuery->whereHas('department.faculty', fn ($q) => $q->where('university_id', $user->university_id));
        }
        $courses = $coursesQuery->get();

        return view('attendance.lecturer-index', compact('sessions', 'courses'));
    }
}
