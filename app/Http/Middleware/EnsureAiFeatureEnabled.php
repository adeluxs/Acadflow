<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AiMode;
use App\Services\Ai\AiRuntimeConfigService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops disabled AI features before module context/retrieval/file extraction is
 * initialized. This keeps the AI Settings feature switch authoritative at the
 * HTTP boundary as well as inside AiManager.
 */
class EnsureAiFeatureEnabled
{
    public function __construct(private readonly AiRuntimeConfigService $runtime) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $declared = (array) config('ai.features', []);
        abort_unless(in_array($feature, $declared, true), 404);

        $user = $request->user();
        $universityId = $user?->university_id ? (int) $user->university_id : null;

        if (! $this->runtime->featureEnabled($feature, $universityId)) {
            return $this->unavailable($request, $feature, 'AI_FEATURE_DISABLED', 'This AI feature is currently unavailable.');
        }

        if ($this->runtime->mode($universityId) === AiMode::DISABLED) {
            return $this->unavailable($request, $feature, 'AI_DISABLED', 'AI assistance is currently unavailable.');
        }

        return $next($request);
    }

    private function unavailable(Request $request, string $feature, string $code, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('ai/context/*')) {
            return new JsonResponse([
                'success' => false,
                'message' => $message,
                'error_code' => $code,
                'feature' => $feature,
            ], 503);
        }

        return response($message, 503);
    }
}
