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
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = Auth::user();

        $subscription = $this->pushService->subscribe(
            $user,
            $request->input('endpoint'),
            $request->input('keys'),
            $request->userAgent()
        );

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
            'endpoint' => 'required|url',
        ]);

        $user = Auth::user();
        
        $this->pushService->unsubscribe($user, $request->input('endpoint'));

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
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('services.webpush.vapid_public_key', ''),
        ]);
    }

    /**
     * Test push notification
     */
    public function test(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $count = $this->pushService->send(
            $user,
            'Test Notification',
            'This is a test push notification from UniAcademic!',
            ['type' => 'test', 'url' => '/dashboard']
        );

        return response()->json([
            'success' => $count > 0,
            'message' => $count > 0 
                ? "Push notification sent to {$count} device(s)" 
                : 'No push subscriptions found',
            'devices_notified' => $count,
        ]);
    }
}