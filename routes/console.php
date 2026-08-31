<?php

use App\Jobs\PublishScheduledKnowledgePublication;
use App\Jobs\RecalculateReputation;
use App\Models\KnowledgePublication;
use App\Models\AcademicEventReminder;
use App\Models\ResearchMeetingReminder;
use App\Models\User;
use App\Services\ResearchMeetingService;
use App\Services\Knowledge\EventChallengeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    KnowledgePublication::query()->where('status','scheduled')->whereNotNull('scheduled_at')->where('scheduled_at','<=',now())->pluck('id')->each(fn(int $id)=>PublishScheduledKnowledgePublication::dispatch($id)->onQueue('default'));
})->everyMinute()->name('publish-scheduled-knowledge')->withoutOverlapping();

Schedule::call(function (): void {
    ResearchMeetingReminder::query()->whereNull('sent_at')->where('remind_at','<=',now())->with('meeting')->chunkById(100,function($reminders){$service=app(ResearchMeetingService::class);foreach($reminders as $reminder)$service->sendReminder($reminder);});
})->everyMinute()->name('research-meeting-reminders')->withoutOverlapping();

Schedule::call(function (): void {
    User::query()->where('is_active',true)->whereHas('knowledgePublications',fn($q)=>$q->where('status','published'))->pluck('id')->each(fn(int $id)=>RecalculateReputation::dispatch($id)->onQueue('analytics'));
})->dailyAt('02:30')->name('recalculate-creator-reputation')->withoutOverlapping();


Schedule::call(function (): void {
    AcademicEventReminder::query()
        ->where('is_active', true)
        ->whereNull('last_dispatched_at')
        ->whereHas('event', fn ($query) => $query->where('status', 'published')->where('starts_at', '>', now()))
        ->with('event')
        ->chunkById(100, function ($reminders): void {
            $service=app(EventChallengeService::class);
            foreach ($reminders as $reminder) $service->dispatchReminder($reminder);
        });
})->everyMinute()->name('academic-event-reminders')->withoutOverlapping();


Schedule::call(function (): void {
    $service = app(EventChallengeService::class);
    $service->advanceEventLifecycles();
    $service->advanceChallengeLifecycles();
})->everyMinute()->name('advance-academic-event-and-challenge-lifecycles')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')->daily();


Schedule::command('acadflow:release-creator-earnings --limit=1000')
    ->hourly()
    ->name('acadflow-release-creator-earnings')
    ->withoutOverlapping();

Artisan::command('acadflow:sync-nigeria-catalog {--csv= : Import an exact institution/faculty/department/course CSV after registry sync} {--no-templates : Do not create starter academic templates}', function (): int {
    /** @var \App\Services\NigeriaAcademicCatalogService $service */
    $service = app(\App\Services\NigeriaAcademicCatalogService::class);

    try {
        $result = $service->syncInstitutions();
        $this->info("Registry sync complete: {$result['nuc']} NUC rows, {$result['nbte']} NBTE rows, {$result['fallback_polytechnics']} fallback polytechnic rows.");
        foreach ($result['warnings'] as $warning) $this->warn($warning);

        if ($csv = $this->option('csv')) {
            $stats = $service->importExactCatalogCsv((string) $csv);
            $this->info('Exact CSV imported: '.json_encode($stats));
        }

        if (! $this->option('no-templates')) {
            $stats = $service->seedStarterTemplatesForAll();
            $this->info('Starter catalogues ensured: '.json_encode($stats));
        }

        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        return self::FAILURE;
    }
})->purpose('Synchronise Nigerian universities/polytechnics and ensure academic catalogue structures');

Artisan::command('acadflow:ai-health {--force : Check now even when scheduled health checking is disabled} {--strict : Exit with failure when any configured provider is unhealthy}', function (): int {
    /** @var \App\Services\Ai\AiRuntimeConfigService $runtime */
    $runtime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    /** @var \App\Ai\AiProviderRegistry $registry */
    $registry = app(\App\Ai\AiProviderRegistry::class);

    if (! $this->option('force') && ! $runtime->providerHealthChecking()) {
        $this->comment('AI provider health checking is disabled in AI Settings.');
        return self::SUCCESS;
    }

    $failed = false;
    foreach (\App\Enums\AiProviderName::cases() as $provider) {
        if ($provider === \App\Enums\AiProviderName::RULE_BASED) continue;
        if (! $runtime->providerEnabled($provider->value) || ! $runtime->providerConfigurationComplete($provider->value)) continue;

        $result = $registry->health($provider->value, null, true);
        $this->line(sprintf('%-16s %-24s %s', $provider->label(), $result['status'] ?? 'unknown', $result['message'] ?? ''));
        if (! empty($result['diagnostic']) && ($result['status'] ?? '') !== 'healthy') {
            $this->comment('  Diagnostic: '.$result['diagnostic']);
        }
        if (($result['status'] ?? '') !== 'healthy') $failed = true;
    }

    // Scheduled monitoring is observational: an upstream provider outage must
    // not make Laravel report the scheduler itself as failed. Operators can use
    // --strict in CI/manual monitoring when a non-zero exit code is desired.
    return ($failed && $this->option('strict')) ? self::FAILURE : self::SUCCESS;
})->purpose('Check configured AcadFlow AI providers and refresh cached health status');

Schedule::command('acadflow:ai-health')
    ->hourly()
    ->name('acadflow-ai-provider-health')
    ->withoutOverlapping();
