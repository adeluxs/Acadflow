<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    protected int $defaultGeofenceRadius = 100;

    protected int $defaultLateThreshold = 15;

    protected int $defaultCheckInWindow = 30;

    public function startSession(
        User $lecturer,
        int $courseId,
        int $semesterId,
        float $latitude,
        float $longitude,
        ?int $geofenceRadius = null,
        ?int $checkInWindow = null
    ): AttendanceSession {
        $session = AttendanceSession::create([
            'course_id' => $courseId,
            'semester_id' => $semesterId,
            'lecturer_id' => $lecturer->id,
            'qr_code' => $this->generateQrCode(),
            'geofence_lat' => $latitude,
            'geofence_lng' => $longitude,
            'geofence_radius' => $geofenceRadius ?? $this->defaultGeofenceRadius,
            'check_in_window' => $checkInWindow ?? $this->defaultCheckInWindow,
            'late_threshold' => $this->defaultLateThreshold,
            'is_active' => true,
        ]);

        return $session;
    }

    public function checkIn(
        AttendanceSession $session,
        User $student,
        float $latitude,
        float $longitude,
        ?string $qrCode = null,
        ?string $deviceFingerprint = null
    ): AttendanceRecord {
        $this->validateCheckIn($session, $student, $latitude, $longitude, $qrCode);

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            $session->geofence_lat,
            $session->geofence_lng
        );

        $isWithinGeofence = $distance <= $session->geofence_radius;
        $minutesLate = $this->calculateMinutesLate($session);
        $status = $this->determineStatus($isWithinGeofence, $minutesLate, $session);

        $record = AttendanceRecord::create([
            'session_id' => $session->id,
            'user_id' => $student->id,
            'status' => $status,
            'check_in_at' => now(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ip_address' => request()->ip(),
            'device_fingerprint' => $deviceFingerprint,
            'is_verified' => $isWithinGeofence,
            'verification_notes' => $isWithinGeofence ?
                'Location verified' :
                "Outside geofence: {$distance}m from center",
        ]);

        Log::info("Student {$student->id} checked in to session {$session->id}", [
            'status' => $status,
            'distance' => $distance,
            'is_within_geofence' => $isWithinGeofence,
        ]);

        return $record;
    }

    public function closeSession(AttendanceSession $session): void
    {
        $session->update([
            'is_active' => false,
            'ended_at' => now(),
        ]);

        $this->markAbsentStudents($session);
    }

    protected function validateCheckIn(
        AttendanceSession $session,
        User $student,
        float $latitude,
        float $longitude,
        ?string $qrCode
    ): void {
        if (! $session->is_active) {
            throw new \InvalidArgumentException('Attendance session is not active');
        }

        if (! $student->enrollments()->where('course_id', $session->course_id)->exists()) {
            throw new \InvalidArgumentException('Student is not enrolled in this course');
        }

        if ($session->qr_code !== $qrCode) {
            throw new \InvalidArgumentException('Invalid QR code');
        }

        $sessionStarted = $session->created_at;
        $qrExpiry = $sessionStarted->addSeconds($session->qr_expiry ?? 300);

        if (now()->isAfter($qrExpiry)) {
            throw new \InvalidArgumentException('QR code has expired');
        }
    }

    protected function determineStatus(bool $isWithinGeofence, int $minutesLate, AttendanceSession $session): string
    {
        if (! $isWithinGeofence) {
            return 'invalid';
        }

        $lateThreshold = $session->late_threshold ?? $this->defaultLateThreshold;

        return $minutesLate <= $lateThreshold ? 'present' : 'late';
    }

    protected function calculateMinutesLate(AttendanceSession $session): int
    {
        $sessionStart = $session->created_at;

        return $sessionStart->diffInMinutes(now());
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    protected function generateQrCode(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function markAbsentStudents(AttendanceSession $session): void
    {
        $enrolledStudentIds = $session->course->enrollments()
            ->where('status', 'enrolled')
            ->pluck('user_id');

        $checkedInStudentIds = $session->records()
            ->pluck('user_id');

        $absentStudentIds = $enrolledStudentIds->diff($checkedInStudentIds);

        foreach ($absentStudentIds as $studentId) {
            AttendanceRecord::firstOrCreate([
                'session_id' => $session->id,
                'user_id' => $studentId,
            ], [
                'status' => 'absent',
                'check_in_at' => null,
            ]);
        }
    }

    public function getAttendanceSummary(AttendanceSession $session): array
    {
        $records = $session->records();

        return [
            'total' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'late' => $records->where('status', 'late')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'invalid' => $records->where('status', 'invalid')->count(),
            'present_rate' => $this->calculatePercentage(
                $records->whereIn('status', ['present', 'late'])->count(),
                $records->count()
            ),
        ];
    }

    protected function calculatePercentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($value / $total) * 100, 2);
    }
}
