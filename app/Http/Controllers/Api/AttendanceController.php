<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceSession::with(['course', 'records']);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'geofence_radius' => 'nullable|integer|min:10|max:1000',
            'check_in_window' => 'nullable|integer|min:5|max:180',
            'late_threshold' => 'nullable|integer|min:1|max:60',
            'qr_refresh_seconds' => 'nullable|integer|min:30|max:300',
        ]);

        $user = Auth::user();
        if (! $user->isLecturer() && ! $user->isAdmin()) {
            return response()->json(['message' => 'Only lecturers or administrators can start attendance sessions.'], 403);
        }

        $semester = Semester::where('is_active', true)->first();
        if (! $semester) {
            return response()->json(['message' => 'No active semester available.'], 422);
        }

        $session = AttendanceSession::create([
            'uuid' => Str::uuid(),
            'course_id' => $validated['course_id'],
            'semester_id' => $semester->id,
            'lecturer_id' => Auth::id(),
            'qr_code' => Str::random(32),
            'qr_expires_at' => now()->addSeconds($validated['qr_refresh_seconds'] ?? 60),
            'started_at' => now(),
            'status' => 'active',
            'geofence_lat' => $validated['latitude'],
            'geofence_lng' => $validated['longitude'],
            'geofence_radius' => $validated['geofence_radius'] ?? 100,
            'check_in_window' => $validated['check_in_window'] ?? 30,
            'late_threshold' => $validated['late_threshold'] ?? 15,
        ]);

        return response()->json($session->load(['course']), 201);
    }

    public function show(AttendanceSession $session)
    {
        return $session->load(['course', 'records.user', 'lecturer']);
    }

    public function update(Request $request, AttendanceSession $session)
    {
        $user = Auth::user();
        if (! $user->isAdmin() && $session->lecturer_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to update this session.'], 403);
        }

        $validated = $request->validate([
            'geofence_radius' => 'nullable|integer|min:10|max:1000',
            'check_in_window' => 'nullable|integer|min:5|max:180',
            'late_threshold' => 'nullable|integer|min:1|max:60',
            'status' => 'nullable|in:active,closed,cancelled',
        ]);

        $session->update($validated);

        return response()->json($session);
    }

    public function closeSession(AttendanceSession $session)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $session->lecturer_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to close this session.'], 403);
        }

        $session->update(['status' => 'closed', 'ended_at' => now()]);

        return response()->json(['message' => 'Session closed']);
    }

    public function qrCode(AttendanceSession $session)
    {
        return response()->json([
            'session_id' => $session->uuid,
            'code' => $session->qr_code,
            'expires_at' => $session->qr_expires_at,
            'qr_data' => base64_encode(json_encode([
                'session_uuid' => $session->uuid,
                'code' => $session->qr_code,
            ])),
        ]);
    }

    public function activeSession(Request $request)
    {
        $query = AttendanceSession::where('status', 'active');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $session = $query->latest('started_at')->first();

        return response()->json($session);
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'session_uuid' => 'required|exists:attendance_sessions,uuid',
            'qr_code' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'device_fingerprint' => 'nullable|string',
        ]);

        $session = AttendanceSession::where('uuid', $validated['session_uuid'])->firstOrFail();

        if ($session->status !== 'active') {
            return response()->json(['message' => 'Session is not active'], 400);
        }

        if ($session->qr_code !== $validated['qr_code']) {
            return response()->json(['message' => 'Invalid QR code'], 400);
        }

        if (now()->greaterThan($session->qr_expires_at)) {
            return response()->json(['message' => 'QR code expired'], 400);
        }

        if (! Enrollment::where('user_id', Auth::id())
            ->where('course_id', $session->course_id)
            ->where('semester_id', $session->semester_id)
            ->where('status', 'enrolled')
            ->exists()) {
            return response()->json(['message' => 'Only enrolled students can check in.'], 403);
        }

        $record = AttendanceRecord::where('session_id', $session->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($record && $record->status !== 'pending') {
            return response()->json(['message' => 'Student has already checked in.'], 400);
        }

        $checkInTime = now();
        if ($checkInTime->greaterThan($session->started_at->addMinutes($session->check_in_window))) {
            return response()->json(['message' => 'Check-in window has closed.'], 400);
        }

        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            $session->geofence_lat,
            $session->geofence_lng
        );

        if ($distance > $session->geofence_radius) {
            return response()->json(['message' => 'Outside geofence area'], 400);
        }

        $status = $checkInTime->lessThanOrEqualTo($session->started_at->addMinutes($session->late_threshold))
            ? 'present'
            : 'late';

        if (! $record) {
            $record = AttendanceRecord::create([
                'session_id' => $session->id,
                'user_id' => Auth::id(),
                'status' => $status,
                'check_in_at' => $checkInTime,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'ip_address' => $request->ip(),
                'device_fingerprint' => $validated['device_fingerprint'] ?? null,
                'is_verified' => true,
            ]);
        } else {
            $record->update([
                'status' => $status,
                'check_in_at' => $checkInTime,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'ip_address' => $request->ip(),
                'device_fingerprint' => $validated['device_fingerprint'] ?? $record->device_fingerprint,
                'is_verified' => true,
            ]);
        }

        return response()->json($record, 201);
    }

    public function myAttendance(Request $request)
    {
        $records = AttendanceRecord::where('user_id', Auth::id())
            ->with('session.course')
            ->orderBy('check_in_at', 'desc')
            ->paginate(20);

        return response()->json($records);
    }

    public function records(Request $request)
    {
        $query = AttendanceRecord::with(['session.course', 'user']);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        return $query->paginate(20);
    }

    public function exportRecords(Request $request)
    {
        $records = AttendanceRecord::with(['session.course', 'user'])
            ->when($request->filled('session_id'), function ($query) use ($request) {
                $query->where('session_id', $request->session_id);
            })
            ->get();

        return response()->json($records);
    }

    public function report(Request $request)
    {
        $query = AttendanceRecord::query();

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        return [
            'total' => $query->count(),
            'present' => $query->where('status', 'present')->count(),
            'late' => $query->where('status', 'late')->count(),
            'absent' => $query->where('status', 'absent')->count(),
        ];
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
