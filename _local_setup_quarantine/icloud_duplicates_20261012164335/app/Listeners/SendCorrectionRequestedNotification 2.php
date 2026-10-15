<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CorrectionRequested;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCorrectionRequestedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(CorrectionRequested $event): void
    {
        $this->notificationService->send(
            $event->student,
            NotificationType::CORRECTION_REQUESTED,
            'Correction Requested',
            "Your submission '{$event->submission->title}' requires corrections. Please review the feedback and resubmit.",
            [
                'submission_id' => $event->submission->uuid,
                'title' => $event->submission->title,
                'url' => route('submissions.show', $event->submission),
            ]
        );
    }
}
