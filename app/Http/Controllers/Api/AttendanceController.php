<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Semester;
use App\Services\AcademicContextService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    use ScopesTenantData;

    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->scopeCourseQuery(AttendanceSession::query()->with(['course', 'records']), $user, 'course');
        if ($user->isStudent()) {
            $query->whereHas('records', fn ($scope) => $scope->where('user_id', $user->id));
        } elseif ($user->isLecturer()) {
            $query->where('lecturer_id', $user->id);
        } elseif (! $user->isAdmin()) abort(403);
        $query->when($request->filled('course_id'), fn ($scope) => $scope->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($scope) => $scope->where('status', $request->string('status')->toString()));
        return $query->latest('started_at')->paginate(20);
    }

    public function store(Request $request, AcademicContextService $academicContext)
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:10|max:1000', 'check_in_window' => 'nullable|integer|min:5|max:180', 'late_threshold' => 'nullable|integer|min:1|max:60',
        ]);
        $user = $request->user(); abort_unless($user->isLecturer() || $user->isAdmin(), 403);
        $course = Course::with('department.faculty')->findOrFail($data['course_id']);
        $this->assertCourseTenant($user, $course);
        abort_unless($user->isAdmin() || $course->lecturerAssignments()->where('user_id', $user->id)->exists(), 403);
        $semester = $academicContext->activeSemesterForCourse($course);
        abort_unless($semester, 422, 'No active semester available.');
        abort_unless($semester->academicSession?->university_id === $course->department?->faculty?->university_id, 422, 'The active semester belongs to another institution.');
        try {
            $session = $this->attendance->startSession($user, $course, $semester, isset($data['latitude']) ? (float) $data['latitude'] : null, isset($data['longitude']) ? (float) $data['longitude'] : null, $data['geofence_radius'] ?? null, $data['check_in_window'] ?? null, $data['late_threshold'] ?? null);
        } catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        return response()->json($session, 201);
    }

    public function show(Request $request, AttendanceSession $session)
    {
        $this->assertSessionAccess($request, $session);
        return $session->load(['course', 'records.user', 'lecturer']);
    }

    public function update(Request $request, AttendanceSession $session)
    {
        $this->assertSessionManager($request, $session);
        $data = $request->validate(['geofence_radius' => 'nullable|integer|min:10|max:1000', 'check_in_window' => 'nullable|integer|min:5|max:180', 'late_threshold' => 'nullable|integer|min:1|max:60']);
        abort_unless($session->status === 'active', 422, 'Only active sessions can be updated.');
        $session->update($data);
        return response()->json($session);
    }

    public function closeSession(Request $request, AttendanceSession $session)
    {
        $this->assertSessionManager($request, $session);
        try { $closed = $this->attendance->closeSession($session); } catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        return response()->json(['message' => 'Session closed', 'session' => $closed]);
    }

    public function qrCode(Request $request, AttendanceSession $session)
    {
        $this->assertSessionManager($request, $session);
        if ($session->qr_expires_at?->isPast()) $session = $this->attendance->refreshQr($session);
        return response()->json(['session_id' => $session->uuid, 'code' => $session->qr_code, 'expires_at' => $session->qr_expires_at, 'qr_data' => base64_encode(json_encode(['session_uuid' => $session->uuid, 'code' => $session->qr_code], JSON_THROW_ON_ERROR))]);
    }

    public function activeSession(Request $request)
    {
        $query = $this->scopeCourseQuery(AttendanceSession::query()->with('course')->where('status', 'active'), $request->user(), 'course');
        if ($request->user()->isStudent()) $query->whereHas('records', fn ($scope) => $scope->where('user_id', $request->user()->id));
        if ($request->filled('course_id')) $query->where('course_id', $request->integer('course_id'));
        return response()->json($query->latest('started_at')->first());
    }

    public function checkIn(Request $request)
    {
        $data = $request->validate(['session_uuid' => 'required|exists:attendance_sessions,uuid', 'qr_code' => 'required|string', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'device_fingerprint' => 'nullable|string|max:255']);
        abort_unless($request->user()->isStudent(), 403);
        $session = AttendanceSession::with('course.department.faculty')->where('uuid', $data['session_uuid'])->firstOrFail();
        $this->assertCourseTenant($request->user(), $session->course);
        try {
            $record = $this->attendance->checkIn($session, $request->user(), $data['qr_code'], isset($data['latitude']) ? (float) $data['latitude'] : null, isset($data['longitude']) ? (float) $data['longitude'] : null, $data['device_fingerprint'] ?? null, $request->ip());
        } catch (InvalidArgumentException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        return response()->json($record, 201);
    }

    public function myAttendance(Request $request)
    {
        return AttendanceRecord::query()->where('user_id', $request->user()->id)->with('session.course')->latest('check_in_at')->paginate(20);
    }

    public function records(Request $request)
    {
        $query = $this->authorizedRecordQuery($request)->with(['session.course', 'user']);
        $query->when($request->filled('session_id'), fn ($scope) => $scope->where('session_id', $request->integer('session_id')));
        return $query->paginate(50);
    }

    public function exportRecords(Request $request)
    {
        $query = $this->authorizedRecordQuery($request)->with(['session.course', 'user']);
        $query->when($request->filled('session_id'), fn ($scope) => $scope->where('session_id', $request->integer('session_id')));
        return response()->json($query->limit(5000)->get());
    }

    public function report(Request $request)
    {
        $query = $this->authorizedRecordQuery($request);
        $query->when($request->filled('session_id'), fn ($scope) => $scope->where('session_id', $request->integer('session_id')));
        return ['total' => (clone $query)->count(), 'present' => (clone $query)->where('status', 'present')->count(), 'late' => (clone $query)->where('status', 'late')->count(), 'absent' => (clone $query)->where('status', 'absent')->count(), 'invalid' => (clone $query)->where('status', 'invalid')->count()];
    }

    private function authorizedRecordQuery(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLecturer(), 403);
        $query = $this->scopeCourseQuery(AttendanceRecord::query(), $request->user(), 'session.course');
        if ($request->user()->isLecturer()) $query->whereHas('session', fn ($scope) => $scope->where('lecturer_id', $request->user()->id));
        return $query;
    }

    private function assertSessionAccess(Request $request, AttendanceSession $session): void
    {
        $session->loadMissing('course.department.faculty'); $this->assertCourseTenant($request->user(), $session->course);
        $allowed = $request->user()->isAdmin() || $session->lecturer_id === $request->user()->id || $session->records()->where('user_id', $request->user()->id)->exists();
        abort_unless($allowed, 403);
    }

    private function assertSessionManager(Request $request, AttendanceSession $session): void
    {
        $session->loadMissing('course.department.faculty'); $this->assertCourseTenant($request->user(), $session->course);
        abort_unless($request->user()->isAdmin() || $session->lecturer_id === $request->user()->id, 403);
    }
}
