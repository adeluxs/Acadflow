<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Commerce\PackageAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidFeatureAccess
{
    public function __construct(private readonly PackageAccessService $packages) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $status = $this->packages->status($user, $feature);
        if (in_array($status['state'], ['free', 'admin', 'sponsored', 'active', 'trial'], true)) {
            return $next($request);
        }

        if ($status['state'] === 'grace' && $request->isMethodSafe()) {
            $request->attributes->set('paid_feature_grace', true);

            return $next($request);
        }

        $definition = (array) config('monetization.pricing_features.'.$feature, []);
        $title = (string) ($definition['title'] ?? str($feature)->replace('_', ' ')->headline());
        $activationUrl = route('commerce.features', ['feature' => $feature]).'#feature-'.$feature;
        $message = $status['state'] === 'grace'
            ? $title.' is in read-only grace access. Renew to create or generate new work.'
            : $title.' requires active paid, trial, or institution-sponsored access.';

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
