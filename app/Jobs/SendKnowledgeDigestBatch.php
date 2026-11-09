<?php

namespace App\Jobs;

use App\Services\Knowledge\KnowledgeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Sends the weekly Knowledge Hub roundup to one batch of up to 500 users. */
class SendKnowledgeDigestBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param list<int> $userIds */
    public function __construct(public readonly array $userIds, public readonly string $week, public readonly string $title, public readonly string $message) {}

    public function handle(KnowledgeNotificationService $service): void
    {
        $service->sendDigestTo($this->userIds, $this->week, $this->title, $this->message);
    }
}
