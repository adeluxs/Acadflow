<?php

namespace App\Jobs;

use App\Models\KnowledgePublication;
use App\Services\Knowledge\KnowledgeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Tells a creator's followers about a newly published guide (duplicate-safe per follower). */
class NotifyKnowledgeFollowers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $publicationId) {}

    public function handle(KnowledgeNotificationService $service): void
    {
        $publication = KnowledgePublication::withoutGlobalScopes()->find($this->publicationId);
        if ($publication) {
            $service->notifyFollowers($publication);
        }
    }
}
