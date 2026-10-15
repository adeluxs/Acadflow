<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceSessionStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public $course;

    public $lecturer;

    public $enrolledStudents;

    public function __construct($session, $course, $lecturer, $enrolledStudents)
    {
        $this->session = $session;
        $this->course = $course;
        $this->lecturer = $lecturer;
        $this->enrolledStudents = $enrolledStudents;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->enrolledStudents as $student) {
            $channels[] = new PrivateChannel('users.'.$student->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'attendance.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->session->uuid,
            'course' => $this->course->name,
            'lecturer' => $this->lecturer->full_name,
            'type' => 'attendance_started',
            'message' => "Attendance session started for {$this->course->name}",
            'qr_code' => $this->session->qr_code,
            'qr_expires_at' => $this->session->qr_expires_at,
        ];
    }
}
