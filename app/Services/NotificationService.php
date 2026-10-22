<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Jobs\DeliverNotification;
use App\Models\Notification;
use App\Models\NotificationEventTemplate;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(private ?PushNotificationService $pushService = null) {}

    public function send(User $user, NotificationType|string $type, string $title, string $message, array $data = []): void
    {
        $this->publish($user, $type, $title, $message, $data);
    }

    public function publish(User $user, NotificationType|string $type, string $title, string $message, array $data = []): ?Notification
    {
        if (! array_key_exists('is_active', $user->getAttributes())) {
            $user = User::withoutGlobalScopes()->find($user->id);
            if (! $user) return null;
        }
        $type = $type instanceof NotificationType ? $type->value : $type;
        if (! $user->is_active || FeatureAccessService::effectiveStatus('notifications', $user->university_id) !== FeatureAccessService::STATUS_ENABLED) return null;
        $preferences = app(NotificationPreferences::class);
        $settings = $preferences->settings($user);
        $category = $preferences->category($type);
        if (! $preferences->allows($settings, $type, $category)) return null;
        $template = NotificationEventTemplate::where('event_type', $type)->first();
        if ($template && ! $template->enabled) return null;
        if ($template) {
            // Deliberately limited placeholders. Never evaluate Blade or PHP
            // supplied by an administrator in notification templates.
            $values = ['{first_name}' => $user->first_name, '{stage}' => (string) ($data['stage'] ?? 'setup'), '{title}' => $title];
            $title = strtr($template->title, $values);
            $message = strtr($template->message, $values);
        }
        if (empty($data['url']) && ! empty($data['route']) && \Illuminate\Support\Facades\Route::has($data['route'])) {
            try { $data['url'] = route($data['route']); } catch (\Throwable) { /* Use notification centre for incomplete route parameters. */ }
        }
        $data['url'] = $preferences->safeUrl($data['url'] ?? null);
        $eventKey = hash('sha256', $user->id.':'.$type.':'.($data['event_key'] ?? json_encode([$title, $message, $data], JSON_THROW_ON_ERROR)));

        return DB::transaction(function () use ($user, $type, $title, $message, $data, $category, $settings, $template, $eventKey): Notification {
            $notification = Notification::firstOrCreate(['event_key' => $eventKey], [
                'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'type' => $type,
                'title' => mb_substr($title, 0, 255), 'message' => $message, 'data' => $data,
                'category' => $category, 'priority' => in_array($data['priority'] ?? null, ['low', 'normal', 'high'], true) ? $data['priority'] : (in_array($type, ['overdue_submission', 'deadline_approaching', 'notification_delivery_alert', 'academic_membership_requested'], true) ? 'high' : 'normal'),
                'university_id' => $data['university_id'] ?? $user->university_id, 'department_id' => $data['department_id'] ?? $user->department_id,
                'in_app_visible' => (bool) ($settings?->in_app_enabled ?? true) && (bool) SettingService::get('in_app_notifications_enabled', true, $user->university_id),
            ]);
            if (! $notification->wasRecentlyCreated) return $notification;
            $tracker = app(NotificationTrackingService::class);
            $tracker->record('created', $user, $notification, metadata: ['stage' => $data['stage'] ?? null], key: 'created:'.$notification->uuid);
            if ($notification->in_app_visible) {
                $database = NotificationLog::create(['uuid' => (string) Str::uuid(), 'delivery_key' => hash('sha256', $notification->uuid.':database'), 'notification_id' => $notification->id, 'user_id' => $user->id, 'channel' => 'database', 'status' => 'delivered', 'accepted_at' => now(), 'delivered_at' => now(), 'attempted_at' => now(), 'attempt_count' => 1, 'latency_ms' => 0]);
                $tracker->record('delivered', $user, $notification, $database, key: 'delivered:'.$database->uuid);
            }
            $channels = $template?->channels ?? ['email', 'push'];
            if (in_array('email', $channels, true) && ($settings?->email_enabled ?? true) && ! $settings?->email_suppressed_at && $user->email_verified_at && SettingService::get('email_notifications_enabled', true, $user->university_id)) {
                $this->queueDelivery($notification, $user, 'email');
            }
            if (in_array('push', $channels, true) && ($settings?->push_enabled ?? true) && app(PushNotificationService::class)->availableFor($user)) {
                PushSubscription::where('user_id', $user->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->select('id')->chunkById(100, function ($subscriptions) use ($notification, $user): void {
                    foreach ($subscriptions as $subscription) $this->queueDelivery($notification, $user, 'push', $subscription->id);
                });
            }

            return $notification;
        }, 3);
    }

    private function queueDelivery(Notification $notification, User $user, string $channel, ?int $subscriptionId = null): void
    {
        $delivery = NotificationLog::firstOrCreate(['delivery_key' => hash('sha256', $notification->uuid.':'.$channel.':'.($subscriptionId ?? 0))], [
            'uuid' => (string) Str::uuid(), 'notification_id' => $notification->id, 'user_id' => $user->id, 'channel' => $channel, 'push_subscription_id' => $subscriptionId,
            'status' => 'queued', 'queued_at' => now(), 'next_attempt_at' => now(), 'attempt_count' => 0,
        ]);
        if (! $delivery->wasRecentlyCreated) return;
        app(NotificationTrackingService::class)->record('queued', $user, $notification, $delivery, key: 'queued:'.$delivery->uuid);
        DeliverNotification::dispatch($delivery->id)->afterCommit();
    }

    // Retained for callers checking the original event-specific preference API.
    protected function shouldSendEmail(NotificationType $type, ?NotificationSetting $settings): bool
    {
        return $settings?->email_enabled ?? true;
    }
}
