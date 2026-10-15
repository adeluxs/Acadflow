<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\NewMaterialUploaded;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendNewMaterialNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(NewMaterialUploaded $event): void
    {
        foreach ($event->students as $student) {
            $this->notificationService->send(
                $student,
                NotificationType::NEW_MATERIAL,
                'New Material Available',
                "New material '{$event->material->title}' has been uploaded to {$event->course->name}.",
                [
                    'material_id' => $event->material->id,
                    'material_title' => $event->material->title,
                    'course_id' => $event->course->id,
                    'course_name' => $event->course->name,
                    'url' => route('materials.show', [$event->course, $event->material]),
                ]
            );
        }
    }
}
