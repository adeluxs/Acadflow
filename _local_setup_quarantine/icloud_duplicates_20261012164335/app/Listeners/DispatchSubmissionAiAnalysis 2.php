<?php

namespace App\Listeners;

use App\Events\SubmissionAiAnalysisRequested;
use App\Jobs\ProcessSubmissionAiAnalysis;

/**
 * Dispatches the background AI analysis job(s) when analysis is requested.
 */
class DispatchSubmissionAiAnalysis
{
    public function handle(SubmissionAiAnalysisRequested $event): void
    {
        foreach ($event->features as $feature) {
            ProcessSubmissionAiAnalysis::dispatch($event->submission, $event->user, $feature)
                ->onConnection(config('ai.queue_connection') ?: config('queue.default'))
                ->onQueue('ai');
        }
    }
}
