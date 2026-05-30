<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\SubmissionConfirmation;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSubmissionConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(SubmissionConfirmation $event): void
    {
        $this->notificationService->send(
            $event->student,
            NotificationType::SUBMISSION_CONFIRMATION,
            'Submission Received',
            "Your submission '{$event->submission->title}' has been received successfully.",
            [
                'submission_id' => $event->submission->uuid,
                'title' => $event->submission->title,
                'url' => route('submissions.show', $event->submission),
            ]
        );
    }
}
