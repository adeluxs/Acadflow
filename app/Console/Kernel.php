<?php

namespace App\Console;

use App\Console\Commands\ProcessSubscriptionRenewals;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ProcessSubscriptionRenewals::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Run subscription renewals daily at midnight
        $schedule->command('subscriptions:process-renewals')->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
