<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();
            return new JsonResponse(['message' => 'This account is inactive.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return new JsonResponse([
                'message' => 'Verify your email address before using protected API features.',
                'next_action' => 'verify_email',
            ], 403);
        }

        if (! $user->onboarding_completed_at) {
            return new JsonResponse([
                'message' => 'Complete onboarding before using protected API features.',
                'next_action' => 'complete_onboarding',
            ], 403);
        }

        if ($user->currentAccessToken() && ! $user->tokenCan('platform:access')) {
            return new JsonResponse([
                'message' => 'Sign in again or complete onboarding through the API to exchange this limited token.',
                'next_action' => 'refresh_access_token',
            ], 403);
        }

        return $next($request);
    }
}
