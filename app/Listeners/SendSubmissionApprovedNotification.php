<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\SubmissionApproved;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSubmissionApprovedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(SubmissionApproved $event): void
    {
        $this->notificationService->send(
            $event->student,
            NotificationType::APPROVAL_GRANTED,
            'Submission Approved',
            "Your submission '{$event->submission->title}' has been approved.",
            [
                'submission_id' => $event->submission->uuid,
                'title' => $event->submission->title,
                'url' => route('submissions.show', $event->submission),
            ]
        );
    }
}
