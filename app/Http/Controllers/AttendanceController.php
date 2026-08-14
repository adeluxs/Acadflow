<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\QrRefreshed;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Services\AcademicContextService;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AcademicContextService $academicContext,
    ) {
    }

    public function startSession(Request $request): RedirectResponse
    {
        $this->authorize('start', AttendanceSession::class);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius' => ['nullable', 'integer', 'between:20,5000'],
            'check_in_window' => ['nullable', 'integer', 'between:1,180'],
            'late_threshold' => ['nullable', 'integer', 'between:0,180'],
        ]);

        $user = $request->user();
        $subscription = $user->activeSubscription()->first();

        if ($subscription?->plan && ! $subscription->plan->allow_attendance_tracking) {
            return back()->with('error', 'Your subscription plan does not allow attendance tracking. Please upgrade your plan.');
        }

        $course = Course::with('department.faculty')->findOrFail($validated['course_id']);
        $semester = $this->academicContext->activeSemesterForCourse($course);

        if (! $semester) {
            return back()->with('error', 'No active semester is configured. Ask an administrator to activate a semester.');
        }

        try {
            $session = $this->attendanceService->startSession(
                $user,
                $course,
                $semester,
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                $validated['geofence_radius'] ?? null,
                $validated['check_in_window'] ?? null,
                $validated['late_threshold'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('attendance.session', $session)
            ->with('success', 'Attendance session started.');
    }

    public function activeSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $session = AttendanceSession::query()
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->where('started_at', '>', now()->subHours(3))
            ->whereHas('course.enrollments', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'enrolled'))
            ->with('course')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return response()->json(['message' => 'No active session is available for your enrolled courses.'], 404);
        }

        $this->authorize('checkIn', $session);

        return response()->json([
            'uuid' => $session->uuid,
            'qr_expires_at' => $session->qr_expires_at,
            'course' => $session->course->name,
            'check_in_url' => $this->checkInUrl($session),
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_uuid' => ['required', 'uuid', 'exists:attendance_sessions,uuid'],
            'qr_code' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        $session = AttendanceSession::query()
            ->where('uuid', $validated['session_uuid'])
            ->with('course')
            ->firstOrFail();

        $this->authorize('checkIn', $session);

        try {
            $record = $this->attendanceService->checkIn(
                $session,
                $request->user(),
                $validated['qr_code'],
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                $validated['device_fingerprint'] ?? $request->userAgent(),
                $request->ip(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('attendance.my')
            ->with('success', 'Check-in successful. Status: '.ucfirst($record->status).'.');
    }

    public function showSession(AttendanceSession $session): View
    {
        $this->authorize('view', $session);

        $session->load(['records.user', 'course', 'lecturer']);
        $summary = $this->attendanceService->getAttendanceSummary($session);
        $qrPayload = $this->checkInUrl($session);

        return view('attendance.session', compact('session', 'summary', 'qrPayload'));
    }

    public function closeSession(AttendanceSession $session): RedirectResponse
    {
        $this->authorize('stop', $session);

        try {
            $this->attendanceService->closeSession($session);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('attendance.lecturer')->with('success', 'Attendance session closed.');
    }

    public function refreshQr(AttendanceSession $session): JsonResponse
    {
        $this->authorize('edit', $session);

        try {
            $session = $this->attendanceService->refreshQr($session);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $qrPayload = $this->checkInUrl($session);

        event(new QrRefreshed(
            $session,
            $qrPayload,
            $session->qr_expires_at->toDateTimeString(),
        ));

        return response()->json([
            'qr_payload' => $qrPayload,
            'qr_expires_at' => $session->qr_expires_at,
        ]);
    }

    public function myAttendance(Request $request): View
    {
        $records = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->with(['session.course'])
            ->latest('id')
            ->paginate(10);

        $checkInSession = null;
        $checkInQrCode = null;

        if ($request->filled(['session_uuid', 'qr_code'])) {
            $checkInSession = AttendanceSession::query()
                ->where('uuid', $request->string('session_uuid'))
                ->where('status', 'active')
                ->with('course')
                ->first();
            $checkInQrCode = $request->string('qr_code')->toString();

            if ($checkInSession) {
                $this->authorize('checkIn', $checkInSession);
            }
        }

        return view('attendance.my', compact('records', 'checkInSession', 'checkInQrCode'));
    }

    public function myAttendanceRecords(Request $request): View
    {
        $query = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->with(['session.course']);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->integer('session_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $records = $query->latest('id')->paginate(20)->withQueryString();

        return view('attendance.records', compact('records'));
    }

    public function exportMyAttendanceRecords(Request $request): JsonResponse
    {
        $query = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->with(['session.course']);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->integer('session_id'));
        }

        return response()->json($query->get());
    }

    public function lecturerSessions(Request $request): View
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $user = $request->user();
        $query = AttendanceSession::query()->with(['course', 'records.user']);

        if ($user->isLecturer()) {
            $query->where('lecturer_id', $user->id);
        } elseif ($user->isDepartmentAdmin()) {
            $query->whereHas('course', fn ($course) => $course->where('department_id', $user->department_id));
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('course.department.faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id));
        }

        $sessions = $query->latest('started_at')->paginate(10);

        $coursesQuery = Course::query()->with('department');
        if ($user->isLecturer()) {
            $coursesQuery->whereHas('lecturerAssignments', fn ($assignment) => $assignment->where('user_id', $user->id));
        } elseif ($user->isDepartmentAdmin()) {
            $coursesQuery->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $coursesQuery->whereHas('department.faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id));
        }

        $courses = $coursesQuery->orderBy('code')->get();

        return view('attendance.lecturer-index', compact('sessions', 'courses'));
    }

    private function checkInUrl(AttendanceSession $session): string
    {
        return route('attendance.my', [
            'session_uuid' => $session->uuid,
            'qr_code' => $session->qr_code,
        ]);
    }
}
