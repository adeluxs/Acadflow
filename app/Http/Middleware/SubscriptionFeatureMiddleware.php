<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionFeatureMiddleware
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Check if user's subscription plan has this feature
        if (! $this->subscriptionService->hasFeature($user, $feature)) {
            // For web requests, redirect with error
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your subscription plan does not include this feature.'], 403);
            }

            return back()->with('error', 'Your subscription plan does not include this feature. Please upgrade your plan.');
        }

        return $next($request);
    }
}
