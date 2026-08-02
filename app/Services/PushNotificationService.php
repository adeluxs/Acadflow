<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to a user
     */
    public function send(User $user, string $title, string $body, array $data = []): int
    {
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
        try {
            $payload = json_encode([
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'icon' => '/icons/icon-192x192.png',
                    'badge' => '/icons/icon-72x72.png',
                    'data' => $data,
                    'vibrate' => [200, 100, 200],
                    'tag' => $data['type'] ?? 'default',
                    'renotify' => true,
                ],
            ]);

            // Note: In production, you'd use a proper VAPID key pair
            // This is a simplified implementation
            $response = Http::withHeaders([
                'TTL' => '86400',
                'Content-Type' => 'application/json',
                'Authorization' => ' vapid t=' . $this->getVapidPublicKey(),
            ])->post($subscription->endpoint, [
                'payload' => base64_encode($payload),
            ]);

            if ($response->successful()) {
                return true;
            }

            // If subscription is no longer valid, delete it
            if ($response->status() === 410 || $response->status() === 404) {
                $subscription->delete();
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Push notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
    public function subscribe(User $user, string $endpoint, array $keys, ?string $userAgent = null): PushSubscription
    {
        // Check if subscription already exists
        $existing = PushSubscription::where('endpoint', $endpoint)->first();
        
        if ($existing) {
            // Update existing subscription
            $existing->update([
                'user_id' => $user->id,
                'keys_p256dh' => $keys['p256dh'] ?? null,
                'keys_auth' => $keys['auth'] ?? null,
                'user_agent' => $userAgent,
                'ip_address' => request()->ip(),
                'expires_at' => null,
            ]);
            
            return $existing;
        }

        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'keys_p256dh' => $keys['p256dh'] ?? null,
            'keys_auth' => $keys['auth'] ?? null,
            'user_agent' => $userAgent,
            'ip_address' => request()->ip(),
            'expires_at' => null,
        ]);
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
        return config('services.webpush.vapid_public_key', '');
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