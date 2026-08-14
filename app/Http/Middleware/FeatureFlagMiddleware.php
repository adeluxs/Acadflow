<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backwards-compatible named middleware. Existing feature.flag:* route
 * declarations now use the same centralized runtime availability service.
 */
class FeatureFlagMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $status = FeatureAccessService::effectiveStatus($feature, $request->user()?->university_id);
        if ($status === FeatureAccessService::STATUS_ENABLED) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            $request->attributes->set('restricted_feature_preview', [
                'feature' => $feature,
                'title' => FeatureAccessService::metadata($feature)['title'] ?? $feature,
                'status' => $status,
            ]);

            return $next($request);
        }

        return FeatureAccessService::unavailableResponse($request, $feature);
    }
}
