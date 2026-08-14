<?php

namespace App\Jobs;

use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledKnowledgePublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $publicationId) {}

    public function handle(ModerationService $moderation): void
    {
        // Do not release scheduled public content while Knowledge Hub is paused.
        // The publication remains scheduled and can be processed after re-enable.
        if (FeatureAccessService::effectiveStatus('knowledge_hub') !== FeatureAccessService::STATUS_ENABLED) {
            return;
        }

        $publication = KnowledgePublication::query()->whereKey($this->publicationId)->first();
        if (! $publication || $publication->status !== 'scheduled' || ! $publication->scheduled_at || $publication->scheduled_at->isFuture()) return;
        $moderator = User::find($publication->moderated_by) ?: $publication->creator;
        if (! $moderator) return;
        $moderation->publish($publication, $moderator, $publication->moderation_note);
    }
}
