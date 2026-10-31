<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** A random, encrypted first-party marker, not a hardware fingerprint. */
class AmbassadorBrowserSignal
{
    public function handle(Request $request, Closure $next)
    {
        $referredRegistration = $request->is('register') && ($request->filled('referral_code')
            || ($request->hasSession() && $request->session()->has('ambassador_referral')));
        if ($referredRegistration || $request->is('r/*', 'ambassador', 'ambassador/apply')) {
            $token = $request->cookie('ambassador_browser');
            if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
                $token = bin2hex(random_bytes(32));
                Cookie::queue(cookie('ambassador_browser', $token, 60 * 24 * 90, '/', null,
                    $request->isSecure(), true, false, 'lax'));
            }
            $request->attributes->set('ambassador_browser', $token);
        }
        return $next($request);
    }
}
