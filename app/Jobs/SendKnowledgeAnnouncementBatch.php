<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Sends one Knowledge Hub announcement to a batch of up to 500 users (duplicate-safe per user). */
class SendKnowledgeAnnouncementBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param list<int> $userIds */
    public function __construct(public readonly array $userIds, public readonly string $type, public readonly string $title, public readonly string $message, public readonly string $url, public readonly string $eventKey) {}

    public function handle(NotificationService $notifications): void
    {
        User::withoutGlobalScopes()->whereIn('id', $this->userIds)->where('is_active', true)->get()
            ->each(fn (User $user) => $notifications->send($user, $this->type, $this->title, $this->message, ['url' => $this->url, 'event_key' => $this->eventKey]));
    }
}
