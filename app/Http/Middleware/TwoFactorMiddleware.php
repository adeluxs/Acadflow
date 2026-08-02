<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->two_factor_secret && !$user->two_factor_confirmed_at) {
            if ($request->route()?->getName() !== 'two-factor.challenge') {
                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
