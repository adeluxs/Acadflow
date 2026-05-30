<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\AssignmentCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAssignmentCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(AssignmentCreated $event): void
    {
        foreach ($event->students as $student) {
            $this->notificationService->send(
                $student,
                NotificationType::ASSIGNMENT_CREATED,
                'New Assignment',
                "A new assignment '{$event->task->title}' has been created for {$event->course->name}. Due: ".$event->task->due_date?->format('M d, Y H:i'),
                [
                    'task_id' => $event->task->id,
                    'title' => $event->task->title,
                    'course_id' => $event->course->id,
                    'course_name' => $event->course->name,
                    'due_date' => $event->task->due_date?->format('M d, Y H:i'),
                    'url' => route('submission-tasks.show', [$event->course, $event->task]),
                ]
            );
        }
    }
}
