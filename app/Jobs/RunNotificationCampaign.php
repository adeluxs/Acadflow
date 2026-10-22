<?php

namespace App\Jobs;

use App\Services\NotificationReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RunNotificationCampaign implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 3600;
    public array $backoff = [60, 300, 900];

    public function __construct(public string $kind)
    {
        $this->onQueue('notifications');
        $this->onConnection(config('notification_delivery.connection', 'database'));
    }
    public function uniqueId(): string { return $this->kind; }
    public function middleware(): array { return [(new WithoutOverlapping('notification-campaign:'.$this->kind))->expireAfter(360)->releaseAfter(60)]; }
    public function handle(NotificationReminderService $reminders): void
    {
        match ($this->kind) { 'onboarding' => $reminders->scanOnboarding(), 'inactivity' => $reminders->scanInactivity(), 'deadlines' => $reminders->scanDeadlines(), default => null };
    }
}
