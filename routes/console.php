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

Schedule::command('research:prune-exports')->daily()->withoutOverlapping();

Schedule::call(function (): void {
    KnowledgePublication::query()->select('id')->where('status','scheduled')->whereNotNull('scheduled_at')->where('scheduled_at','<=',now())->chunkById(250, function ($publications): void { foreach ($publications as $publication) PublishScheduledKnowledgePublication::dispatch($publication->id)->onQueue('default'); });
})->everyMinute()->name('publish-scheduled-knowledge')->withoutOverlapping();

Schedule::call(function (): void {
    ResearchMeetingReminder::query()->whereNull('sent_at')->where('remind_at','<=',now())->with('meeting')->chunkById(100,function($reminders){$service=app(ResearchMeetingService::class);foreach($reminders as $reminder)$service->sendReminder($reminder);});
})->everyMinute()->name('research-meeting-reminders')->withoutOverlapping();

Schedule::call(function (): void {
    User::query()->select('id')->where('is_active',true)->whereHas('knowledgePublications',fn($q)=>$q->where('status','published'))->chunkById(250, function ($users): void { foreach ($users as $user) RecalculateReputation::dispatch($user->id)->onQueue('analytics'); });
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

Artisan::command('acadflow:sync-nigeria-catalog {--csv= : Import an exact institution/faculty/department/course CSV after registry sync} {--no-templates : Do not create starter academic templates} {--templates-only : Resume starter templates without syncing or changing existing institution registry rows}', function (): int {
    /** @var \App\Services\NigeriaAcademicCatalogService $service */
    $service = app(\App\Services\NigeriaAcademicCatalogService::class);

    if ($this->option('templates-only') && ($this->option('no-templates') || $this->option('csv'))) {
        $this->error('--templates-only cannot be combined with --no-templates or --csv.');
        return self::FAILURE;
    }

    try {
        if (! $this->option('templates-only')) {
            $result = $service->syncInstitutions();
            $this->info("Registry sync complete: {$result['nuc']} NUC rows, {$result['nbte']} NBTE rows, {$result['fallback_polytechnics']} fallback polytechnic rows.");
            foreach ($result['warnings'] as $warning) $this->warn($warning);
        }

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
})->purpose('Check configured AcadSyntra AI providers and refresh cached health status');

Schedule::command('acadflow:ai-health')
    ->hourly()
    ->name('acadflow-ai-provider-health')
    ->withoutOverlapping();

// Proactive deadline coach: for students with deadlines in the next three days,
// generate an advisory action plan. Gated by the study_planner AI feature and
// capped so one run cannot fan out unbounded provider usage.
Schedule::call(function (): void {
    if (! app(\App\Services\Ai\AiRuntimeConfigService::class)->featureEnabled('study_planner')) {
        return;
    }

    $dueTasks = \App\Models\SubmissionTask::query()
        ->where('status', 'published')
        ->where(function ($query): void {
            $query->whereNull('close_at')->orWhere('close_at', '>=', now());
        })
        ->where(function ($query): void {
            $query->where('close_at', '<=', now()->addDays(3))
                ->orWhere('due_date', '<=', now()->addDays(3));
        })
        ->get(['id', 'course_id']);

    $budget = 50;
    $service = app(\App\Services\Student\DeadlineCoachService::class);

    foreach ($dueTasks->groupBy('course_id') as $courseId => $rows) {
        if ($budget <= 0) break;

        $course = \App\Models\Course::find($courseId);
        if (! $course) continue;

        $studentIds = \App\Models\Enrollment::query()
            ->where('course_id', $courseId)
            ->where('status', 'enrolled')
            ->pluck('user_id');

        foreach ($studentIds as $studentId) {
            if ($budget <= 0) break;
            $student = \App\Models\User::find($studentId);
            if (! $student) continue;

            try {
                $service->generate($student, $course);
                $budget--;
            } catch (\Throwable) {
                // Best-effort automation; never fail the scheduler.
            }
        }
    }
})->dailyAt('07:00')->name('acadflow-deadline-coach')->withoutOverlapping();

// Assignment deadline reminders. The lead time is configurable via the
// `assignment_reminder_hours` setting (default 24h). A second pass notifies
// students about assignments that have passed their deadline with no
// submission, so overdue work is not silently missed.
Schedule::call(function (): void {
    $now = now();
    $leadHours = (int) (\App\Services\SettingService::get('assignment_reminder_hours', 24) ?: 24);
    $windowEnd = $now->copy()->addHours($leadHours);

    $nonSubmitters = function (\App\Models\SubmissionTask $task) {
        $submittedIds = \App\Models\Submission::query()
            ->where('submission_task_id', $task->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return \App\Models\User::query()
            ->whereHas('enrollments', function ($query) use ($task): void {
                $query->where('course_id', $task->course_id)
                    ->where('semester_id', $task->semester_id)
                    ->where('status', 'enrolled');
            })
            ->whereNotIn('id', $submittedIds ?: [0])
            ->get();
    };

    // Upcoming reminders.
    \App\Models\SubmissionTask::query()
        ->where('status', 'published')
        ->where('is_visible_to_students', true)
        ->where(function ($query) use ($now, $windowEnd): void {
            $query->whereBetween('close_at', [$now, $windowEnd])
                ->orWhereBetween('due_date', [$now, $windowEnd]);
        })
        ->with('course:id,name')
        ->chunkById(100, function ($tasks) use ($nonSubmitters): void {
            foreach ($tasks as $task) {
                $students = $nonSubmitters($task);
                if ($students->isNotEmpty()) {
                    event(new \App\Events\DeadlineApproaching($task, $students));
                }
            }
        });

    // Overdue reminders.
    $notifications = app(\App\Services\NotificationService::class);
    \App\Models\SubmissionTask::query()
        ->where('status', 'published')
        ->where('is_visible_to_students', true)
        ->whereNotNull('close_at')
        ->where('close_at', '<', $now)
        ->with('course:id,name')
        ->chunkById(100, function ($tasks) use ($nonSubmitters, $notifications): void {
            foreach ($tasks as $task) {
                foreach ($nonSubmitters($task) as $student) {
                    $notifications->send(
                        $student,
                        \App\Enums\NotificationType::OVERDUE_SUBMISSION,
                        'Assignment overdue',
                        "The deadline for '{$task->title}' has passed and no submission was received.",
                        [
                            'task_id' => $task->uuid,
                            'title' => $task->title,
                            'url' => route('submission-tasks.student.show', ['course' => $task->course ?? $task->course_id, 'task' => $task]),
                        ]
                    );
                }
            }
        });
})->dailyAt('08:00')->name('assignment-deadline-reminders')->withoutOverlapping();
