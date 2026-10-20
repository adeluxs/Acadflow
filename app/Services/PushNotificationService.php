<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use App\Support\PushEndpoint;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to a user
     */
    public function send(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->availableFor($user) || ! (\App\Models\NotificationSetting::query()->where('user_id', $user->id)->value('push_enabled') ?? true)) return 0;
        $subscriptions = PushSubscription::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $successCount = 0;
        foreach ($subscriptions as $subscription) {
            if ($this->sendToSubscription($subscription, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Send push notification to a specific subscription
     */
    public function sendToSubscription(PushSubscription $subscription, string $title, string $body, array $data = []): bool
    {
        if (! $subscription->isValid() || ! PushEndpoint::valid($subscription->endpoint) || ! $this->availableFor($subscription->user)
            || ! (\App\Models\NotificationSetting::query()->where('user_id', $subscription->user_id)->value('push_enabled') ?? true)) return false;
        try {
            $payload = json_encode([
                'title' => mb_substr($title, 0, 150), 'body' => mb_substr($body, 0, 500),
                'icon' => '/branding/acadsyntra-icon-192.png', 'badge' => '/branding/acadsyntra-mark.png',
                'tag' => 'acadflow-'.($data['notification_id'] ?? $data['type'] ?? 'notification'),
                'data' => ['url' => $data['url'] ?? '/notifications'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $report = app(WebPush::class)->sendOneNotification(Subscription::create([
                'endpoint' => $subscription->endpoint, 'keys' => ['p256dh' => $subscription->keys_p256dh, 'auth' => $subscription->keys_auth],
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
            if ($report->isSubscriptionExpired()) $subscription->delete();
            if (! $report->isSuccess()) Log::warning('Browser push delivery rejected', ['subscription_id' => $subscription->id, 'http_status' => $report->getResponse()?->getStatusCode()]);
            return $report->isSuccess();
        } catch (\Throwable $exception) {
            Log::warning('Browser push delivery failed', ['subscription_id' => $subscription->id, 'exception_class' => $exception::class]);
            return false;
        }
    }

    public function availableFor(User $user): bool
    {
        return $user->is_active && FeatureAccessService::effectiveStatus('push_notifications', $user->university_id) === FeatureAccessService::STATUS_ENABLED
            && (bool) SettingService::get('push_notifications_enabled', false, $user->university_id)
            && app(WebPushConfiguration::class)->isConfigured();
    }

    /**
     * Send push notification to multiple users
     */
    public function sendToMany(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [];
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $results[$userId] = $this->send($user, $title, $body, $data);
            }
        }

        return $results;
    }

    /**
     * Send push notification to all users in a course
     */
    public function sendToCourse(int $courseId, string $title, string $body, array $data = []): int
    {
        $enrolledUserIds = \App\Models\Enrollment::where('course_id', $courseId)
            ->where('status', 'enrolled')
            ->pluck('user_id')
            ->toArray();

        return array_sum($this->sendToMany($enrolledUserIds, $title, $body, $data));
    }

    /**
     * Store a new push subscription for a user
     */
    public function subscribe(User $user, string $endpoint, array $keys, ?string $userAgent = null, ?int $expirationTime = null): PushSubscription
    {
        if (! PushEndpoint::valid($endpoint)) throw ValidationException::withMessages(['endpoint' => 'Use a supported browser push service over HTTPS.']);
        if (! PushEndpoint::validKeys($keys)) {
            throw ValidationException::withMessages(['keys' => 'The browser subscription encryption keys are invalid.']);
        }
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $endpoint, $keys, $userAgent, $expirationTime): PushSubscription {
            $hash = hash('sha256', $endpoint);
            $existing = PushSubscription::query()->where('endpoint_hash', $hash)->lockForUpdate()->first();
            if ($existing && $existing->user_id !== $user->id
                && (! hash_equals((string) $existing->keys_p256dh, $keys['p256dh']) || ! hash_equals((string) $existing->keys_auth, $keys['auth']))) {
                throw ValidationException::withMessages(['endpoint' => 'This subscription belongs to a different browser session.']);
            }
            $attributes = [
                'endpoint' => $endpoint, 'endpoint_hash' => $hash, 'user_id' => $user->id, 'keys_p256dh' => $keys['p256dh'], 'keys_auth' => $keys['auth'],
                'user_agent' => mb_substr($userAgent ?? '', 0, 255), 'ip_address' => request()->ip(),
                'expires_at' => $expirationTime !== null ? \Illuminate\Support\Carbon::createFromTimestampMs($expirationTime) : null,
            ];
            if ($existing) { $existing->update($attributes); return $existing; }
            // A concurrent insert may fail its unique constraint; never silently
            // overwrite another user's keys through an upsert fallback.
            return PushSubscription::create($attributes);
        }, 3);
    }

    /**
     * Unsubscribe a user from push notifications
     */
    public function unsubscribe(User $user, string $endpoint): bool
    {
        $subscription = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->first();

        if ($subscription) {
            $subscription->delete();
            return true;
        }

        return false;
    }

    /**
     * Get VAPID public key for client
     */
    public function getVapidPublicKey(): string
    {
        // In production, this would be your actual VAPID public key
        // You can generate one using web-push library
        return app(WebPushConfiguration::class)->auth()['publicKey'];
    }

    /**
     * Clean up expired subscriptions
     */
    public function cleanup(): int
    {
        return PushSubscription::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }
}