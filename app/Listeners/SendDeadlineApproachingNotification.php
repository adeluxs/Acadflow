<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DeadlineApproaching;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDeadlineApproachingNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(DeadlineApproaching $event): void
    {
        foreach ($event->students as $student) {
            $this->notificationService->send(
                $student,
                NotificationType::DEADLINE_APPROACHING,
                'Deadline Approaching',
                "The deadline for '{$event->submissionTask->title}' is in 24 hours. Don't forget to submit!",
                [
                    'task_id' => $event->submissionTask->uuid,
                    'title' => $event->submissionTask->title,
                    'due_date' => $event->submissionTask->due_date?->format('M d, Y H:i'),
                    'url' => route('submission-tasks.show', [$event->submissionTask->course, $event->submissionTask]),
                ]
            );
        }
    }
}
