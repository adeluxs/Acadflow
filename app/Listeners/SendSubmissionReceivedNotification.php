<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\SubmissionSubmitted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSubmissionReceivedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(SubmissionSubmitted $event): void
    {
        $this->notificationService->send(
            $event->lecturer,
            NotificationType::SUBMISSION_RECEIVED,
            'New Submission Received',
            "{$event->submission->user->full_name} submitted '{$event->submission->title}' for {$event->course->name}",
            [
                'submission_id' => $event->submission->uuid,
                'title' => $event->submission->title,
                'student' => $event->submission->user->full_name,
                'url' => route('lecturer.submission.review', $event->submission),
            ]
        );
    }
}
