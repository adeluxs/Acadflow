<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\NewDiscussionPosted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDiscussionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(NewDiscussionPosted $event): void
    {
        $type = $event->isReply ? NotificationType::NEW_REPLY : NotificationType::NEW_QUESTION;
        $title = $event->isReply ? 'New Reply' : 'New Question';

        foreach ($event->recipients as $recipient) {
            $this->notificationService->send(
                $recipient,
                $type,
                $title,
                $event->isReply
                    ? "New reply in discussion: '{$event->discussion->title}'"
                    : "New question posted: '{$event->discussion->title}' in {$event->course->name}",
                [
                    'discussion_id' => $event->discussion->id,
                    'title' => $event->discussion->title,
                    'course_id' => $event->course->id,
                    'course_name' => $event->course->name,
                    'url' => route('discussions.show', [$event->course, $event->discussion]),
                ]
            );
        }
    }
}
