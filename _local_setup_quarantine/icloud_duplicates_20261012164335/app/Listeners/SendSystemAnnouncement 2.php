<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\SystemAnnouncementBroadcast;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSystemAnnouncement implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(SystemAnnouncementBroadcast $event): void
    {
        foreach ($event->recipients as $recipient) {
            $this->notificationService->send(
                $recipient,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                $event->title,
                $event->message,
                [
                    'title' => $event->title,
                    'message' => $event->message,
                    'sender' => $event->sender?->full_name ?? 'System',
                ]
            );
        }
    }
}
