<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->onboarding_completed_at) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Complete onboarding before using this feature.',
                    'onboarding_url' => route('onboarding.show'),
                ], 409);
            }

            return redirect()->route('onboarding.show')
                ->with('info', 'Complete your profile so AcadFlow can personalize your workspace.');
        }

        return $next($request);
    }
}
