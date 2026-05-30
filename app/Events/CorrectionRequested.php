<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CorrectionRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $submission;

    public $student;

    public function __construct($submission, $student)
    {
        $this->submission = $submission;
        $this->student = $student;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->student->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'submission.correction_requested';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->submission->uuid,
            'title' => $this->submission->title,
            'course' => $this->submission->course->name,
            'lecturer' => auth()->user()->full_name,
            'type' => 'correction_requested',
            'message' => "Correction requested for: {$this->submission->title}",
            'url' => route('submissions.show', $this->submission),
        ];
    }
}
