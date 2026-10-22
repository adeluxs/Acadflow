<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use App\Services\WebPushConfiguration;
use Illuminate\Console\Command;

class DiagnoseNotifications extends Command
{
    protected $signature = 'notifications:diagnose {--json : Print a secret-free configuration and queue report}';
    protected $description = 'Inspect notification readiness without sending email or contacting browser push services';

    public function handle(): int
    {
        $report = [
            'mail_transport' => config('mail.default'),
            'email_is_simulated' => in_array(config('mail.default'), ['array', 'log'], true),
            'smtp_credentials_present' => (bool) config('mail.mailers.smtp.username') && (bool) config('mail.mailers.smtp.password'),
            'queue_connection' => config('notification_delivery.connection'), 'delivery_queue' => config('notification_delivery.queue'),
            'notifications_module' => FeatureAccessService::effectiveStatus('notifications'),
            'push_module' => FeatureAccessService::effectiveStatus('push_notifications'),
            'push_channel_enabled' => (bool) SettingService::getGlobal('push_notifications_enabled', false),
            'vapid_configured' => app(WebPushConfiguration::class)->isConfigured(),
            'public_url_https' => str_starts_with(config('app.url'), 'https://'),
            'active_devices' => PushSubscription::where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'pending_deliveries' => NotificationLog::whereIn('status', ['queued', 'deferred', 'retrying'])->count(),
            'uncertain_outcomes' => NotificationLog::where('status', 'unknown')->count(),
        ];
        if ($this->option('json')) $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        else {
            $this->table(['Check', 'Recorded configuration'], collect($report)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])->values()->all());
            $this->line('No delivery was attempted. Readiness does not confirm inbox delivery or device display.');
        }

        return self::SUCCESS;
    }
}
