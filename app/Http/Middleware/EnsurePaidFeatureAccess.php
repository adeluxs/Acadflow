<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Commerce\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidFeatureAccess
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($this->entitlements->has($user, $feature)) {
            return $next($request);
        }

        $definition = (array) config('monetization.pricing_features.'.$feature, []);
        $title = (string) ($definition['title'] ?? str($feature)->replace('_', ' ')->headline());
        $activationUrl = route('commerce.features', ['feature' => $feature]).'#feature-'.$feature;
        $message = $title.' requires active paid or institution-sponsored access.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'feature' => $feature,
                'activation_url' => $activationUrl,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return redirect()->to($activationUrl)->withErrors(['feature' => $message]);
    }
}
