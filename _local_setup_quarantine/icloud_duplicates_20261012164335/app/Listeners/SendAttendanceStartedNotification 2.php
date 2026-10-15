<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\AttendanceSessionStarted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAttendanceStartedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(AttendanceSessionStarted $event): void
    {
        foreach ($event->enrolledStudents as $student) {
            $this->notificationService->send(
                $student,
                NotificationType::ATTENDANCE_STARTED,
                'Attendance Session Started',
                "Attendance session for {$event->course->name} is now active. Scan the QR code in class to mark your attendance.",
                [
                    'session_uuid' => $event->session->uuid,
                    'course_id' => $event->course->id,
                    'qr_code' => $event->session->qr_code,
                ]
            );
        }
    }
}
