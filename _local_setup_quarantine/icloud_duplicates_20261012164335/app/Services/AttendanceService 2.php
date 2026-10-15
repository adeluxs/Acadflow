<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttendanceService
{
    private const DEFAULT_GEOFENCE_RADIUS = 100;

    private const DEFAULT_LATE_THRESHOLD = 15;

    private const DEFAULT_CHECK_IN_WINDOW = 30;

    private const DEFAULT_QR_LIFETIME_SECONDS = 60;

    public function startSession(
        User $lecturer,
        Course $course,
        Semester $semester,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $geofenceRadius = null,
        ?int $checkInWindow = null,
        ?int $lateThreshold = null,
    ): AttendanceSession {
        if (! $lecturer->canAccessCourse($course)) {
            throw new InvalidArgumentException('You are not assigned to this course.');
        }

        $activeSessionExists = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if ($activeSessionExists) {
            throw new InvalidArgumentException('This course already has an active attendance session.');
        }

        return DB::transaction(function () use (
            $lecturer,
            $course,
            $semester,
            $latitude,
            $longitude,
            $geofenceRadius,
            $checkInWindow,
            $lateThreshold,
        ): AttendanceSession {
            $session = AttendanceSession::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'lecturer_id' => $lecturer->id,
                'qr_code' => $this->generateQrCode(),
                'qr_expires_at' => now()->addSeconds(self::DEFAULT_QR_LIFETIME_SECONDS),
                'started_at' => now(),
                'status' => 'active',
                'geofence_lat' => $latitude,
                'geofence_lng' => $longitude,
                'geofence_radius' => $geofenceRadius ?? self::DEFAULT_GEOFENCE_RADIUS,
                'check_in_window' => $checkInWindow ?? self::DEFAULT_CHECK_IN_WINDOW,
                'late_threshold' => $lateThreshold ?? self::DEFAULT_LATE_THRESHOLD,
            ]);

            $course->enrollments()
                ->where('status', 'enrolled')
                ->pluck('user_id')
                ->each(function (int $studentId) use ($session): void {
                    AttendanceRecord::firstOrCreate([
                        'session_id' => $session->id,
                        'user_id' => $studentId,
                    ], [
                        'status' => 'pending',
                        'check_in_at' => null,
                    ]);
                });

            return $session->fresh(['course', 'semester', 'lecturer', 'records']);
        });
    }

    public function checkIn(
        AttendanceSession $session,
        User $student,
        string $qrCode,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $deviceFingerprint = null,
        ?string $ipAddress = null,
    ): AttendanceRecord {
        $this->validateCheckIn($session, $student, $qrCode);

        return DB::transaction(function () use (
            $session,
            $student,
            $latitude,
            $longitude,
            $deviceFingerprint,
            $ipAddress,
        ): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('session_id', $session->id)
                ->where('user_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                throw new InvalidArgumentException('No attendance record exists for this student.');
            }

            if ($record->status !== 'pending') {
                throw new InvalidArgumentException('You have already checked in to this session.');
            }

            [$isVerified, $verificationNotes] = $this->verifyLocation($session, $latitude, $longitude);
            $minutesSinceStart = max(0, $session->started_at->diffInMinutes(now()));
            $status = $minutesSinceStart <= $session->late_threshold ? 'present' : 'late';

            if (! $isVerified && $session->hasGeofence()) {
                $status = 'invalid';
            }

            $record->update([
                'status' => $status,
                'check_in_at' => now(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'ip_address' => $ipAddress,
                'device_fingerprint' => $deviceFingerprint,
                'is_verified' => $isVerified,
                'verification_notes' => $verificationNotes,
            ]);

            Log::info('Attendance check-in recorded.', [
                'session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
                'is_verified' => $isVerified,
            ]);

            return $record->fresh(['session.course', 'user']);
        });
    }

    public function closeSession(AttendanceSession $session): AttendanceSession
    {
        if ($session->status !== 'active') {
            throw new InvalidArgumentException('This attendance session is no longer active.');
        }

        return DB::transaction(function () use ($session): AttendanceSession {
            $session->update([
                'status' => 'closed',
                'ended_at' => now(),
            ]);

            $session->records()
                ->where('status', 'pending')
                ->update([
                    'status' => 'absent',
                    'check_in_at' => null,
                    'updated_at' => now(),
                ]);

            return $session->fresh(['records']);
        });
    }

    public function refreshQr(AttendanceSession $session): AttendanceSession
    {
        if ($session->status !== 'active') {
            throw new InvalidArgumentException('Only active attendance sessions can refresh their QR code.');
        }

        $session->update([
            'qr_code' => $this->generateQrCode(),
            'qr_expires_at' => now()->addSeconds(self::DEFAULT_QR_LIFETIME_SECONDS),
        ]);

        return $session->fresh();
    }

    public function getAttendanceSummary(AttendanceSession $session): array
    {
        $counts = $session->records()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $attended = (int) (($counts['present'] ?? 0) + ($counts['late'] ?? 0));

        return [
            'total' => $total,
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'invalid' => (int) ($counts['invalid'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'present_rate' => $total === 0 ? 0.0 : round(($attended / $total) * 100, 2),
        ];
    }

    private function validateCheckIn(AttendanceSession $session, User $student, string $qrCode): void
    {
        if ($session->status !== 'active' || $session->ended_at !== null) {
            throw new InvalidArgumentException('Attendance session is not active.');
        }

        if (! hash_equals((string) $session->qr_code, $qrCode)) {
            throw new InvalidArgumentException('The QR code is invalid.');
        }

        if ($session->qr_expires_at === null || now()->isAfter($session->qr_expires_at)) {
            throw new InvalidArgumentException('The QR code has expired. Ask the lecturer to refresh it.');
        }

        if ($session->started_at->copy()->addMinutes($session->check_in_window)->isPast()) {
            throw new InvalidArgumentException('The attendance check-in window has closed.');
        }

        if (! $student->enrollments()
            ->where('course_id', $session->course_id)
            ->where('status', 'enrolled')
            ->exists()) {
            throw new InvalidArgumentException('You are not enrolled in this course.');
        }
    }

    private function verifyLocation(
        AttendanceSession $session,
        ?float $latitude,
        ?float $longitude,
    ): array {
        if (! $session->hasGeofence()) {
            return [true, 'No geofence is configured for this session.'];
        }

        if ($latitude === null || $longitude === null) {
            return [false, 'Location is required for this attendance session.'];
        }

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            (float) $session->geofence_lat,
            (float) $session->geofence_lng,
        );

        $withinGeofence = $distance <= $session->geofence_radius;

        return [
            $withinGeofence,
            $withinGeofence
                ? sprintf('Location verified (%.1f metres from session centre).', $distance)
                : sprintf('Outside geofence (%.1f metres from session centre).', $distance),
        ];
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6_371_000;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function generateQrCode(): string
    {
        return Str::random(48);
    }
}
