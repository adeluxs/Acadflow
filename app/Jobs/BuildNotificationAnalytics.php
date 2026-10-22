<?php

namespace App\Jobs;

use App\Services\NotificationAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;

class BuildNotificationAnalytics implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;
    public int $uniqueFor = 120;
    public int $tries = 2;
    public function __construct() { $this->onConnection(config('notification_delivery.connection', 'database'))->onQueue('analytics'); }
    public function handle(NotificationAnalyticsService $analytics): void
    {
        $analytics->snapshot(['from' => CarbonImmutable::now()->subDays(29)->startOfDay(), 'to' => CarbonImmutable::now()->endOfDay()]);
    }
}
