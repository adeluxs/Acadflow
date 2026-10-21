<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushNotificationController extends Controller
{
    public function __construct(
        private PushNotificationService $pushService
    ) {}

    /**
     * Store push subscription
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:4096'],
            'keys.p256dh' => 'required|string|max:120',
            'keys.auth' => 'required|string|max:32',
            'expirationTime' => 'nullable|integer|min:0|max:2147483647000',
            'enablePreference' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        abort_unless($this->pushService->availableFor($user), 503, 'Browser push is not enabled or configured.');

        $subscription = $this->pushService->subscribe(
            $user,
            $request->input('endpoint'),
            $request->input('keys'),
            $request->userAgent(),
            $request->input('expirationTime')
        );

        if ($request->hasSession()) {
            $request->session()->put('browser_push.endpoint_hash', $subscription->endpoint_hash);
        }

        if ($request->boolean('enablePreference')) \App\Models\NotificationSetting::updateOrCreate(['user_id' => $user->id], ['push_enabled' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved',
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:4096'],
        ]);

        $user = Auth::user();
        
        $this->pushService->unsubscribe($user, $request->input('endpoint'));

        if ($request->hasSession() && $request->session()->get('browser_push.endpoint_hash') === hash('sha256', $request->input('endpoint'))) {
            $request->session()->forget('browser_push.endpoint_hash');
        }

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed',
        ]);
    }

    /**
     * Get user's push subscriptions
     */
    public function subscriptions(): JsonResponse
    {
        $user = Auth::user();
        
        $subscriptions = PushSubscription::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'endpoint', 'user_agent', 'created_at']);

        return response()->json([
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Get VAPID public key for subscription
     */
    public function vapidPublicKey(Request $request): JsonResponse
    {
        $available = $this->pushService->availableFor($request->user());
        return response()->json(['available' => $available, 'configured' => app(\App\Services\WebPushConfiguration::class)->isConfigured(), 'publicKey' => $available ? $this->pushService->getVapidPublicKey() : null]);
    }

    /**
     * Test push notification
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->pushService->availableFor($user), 503, 'Browser push is not enabled or configured.');

        $count = $this->pushService->send(
            $user,
            'Test Notification',
            'This is a test push notification from AcadSyntra!',
            ['type' => 'test', 'url' => '/dashboard']
        );

        return response()->json([
            'success' => $count > 0,
            'message' => $count > 0 
                ? "Push notification sent to {$count} device(s)" 
                : 'No device accepted the test notification. Enable this browser or review delivery configuration.',
            'devices_notified' => $count,
        ], $count > 0 ? 200 : 422);
    }
}
