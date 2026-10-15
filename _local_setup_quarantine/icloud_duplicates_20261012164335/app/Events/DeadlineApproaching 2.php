<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeadlineApproaching
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $submissionTask;

    public $students;

    public function __construct($submissionTask, $students)
    {
        $this->submissionTask = $submissionTask;
        $this->students = $students;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->students as $student) {
            $channels[] = new PrivateChannel('users.'.$student->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'deadline.approaching';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->submissionTask->uuid,
            'title' => $this->submissionTask->title,
            'course' => $this->submissionTask->course->name,
            'due_date' => $this->submissionTask->due_date?->format('M d, Y H:i'),
            'type' => 'deadline_approaching',
            'message' => "Deadline approaching: {$this->submissionTask->title}",
            'url' => route('submission-tasks.student.show', [$this->submissionTask->course, $this->submissionTask]),
        ];
    }
}
