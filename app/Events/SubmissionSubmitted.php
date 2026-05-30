<?php

namespace App\Events;

use App\Enums\NotificationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $submission;

    public $course;

    public $lecturer;

    public function __construct($submission, $course, $lecturer)
    {
        $this->submission = $submission;
        $this->course = $course;
        $this->lecturer = $lecturer;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->lecturer->id),
            new PrivateChannel('course.'.$this->course->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'submission.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->submission->uuid,
            'title' => $this->submission->title,
            'course' => $this->course->name,
            'student' => $this->submission->user->full_name,
            'type' => NotificationType::SUBMISSION_RECEIVED->value,
            'message' => "New submission: {$this->submission->title} from {$this->submission->user->full_name}",
            'url' => route('lecturer.submission.review', $this->submission),
        ];
    }
}
