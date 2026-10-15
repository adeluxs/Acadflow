<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->two_factor_secret && ! $this->hasValidSession($request)) {
            $request->session()->forget([
                'auth.two_factor_passed',
                'auth.two_factor_user_id',
                'auth.two_factor_passed_at',
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication is required.',
                    'challenge_url' => route('two-factor.challenge'),
                ], 423);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }

    private function hasValidSession(Request $request): bool
    {
        $user = $request->user();
        $passedAt = $request->session()->get('auth.two_factor_passed_at');
        $ttlHours = max(1, (int) config('auth.two_factor_session_hours', 12));

        return (bool) $request->session()->get('auth.two_factor_passed')
            && (int) $request->session()->get('auth.two_factor_user_id') === (int) $user?->getKey()
            && is_string($passedAt)
            && Carbon::parse($passedAt)->addHours($ttlHours)->isFuture();
    }
}
