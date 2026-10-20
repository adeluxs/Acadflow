<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerificationRequirement
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null)
    {
        if (! $request->user()?->hasRequiredEmailVerification()) {
            if ($request->expectsJson()) abort(403, 'Your email address is not verified.');
            return redirect()->guest(route($redirectToRoute ?: 'verification.notice'));
        }
        return $next($request);
    }
}
