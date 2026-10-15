<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConcurrentSessionLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && config('session.driver') === 'database') {
            $maxSessions = max(1, (int) SettingService::get('max_concurrent_sessions', 3, $user->university_id));
            $timeoutMinutes = max(1, (int) SettingService::get('session_timeout_minutes', (int) config('session.lifetime', 120), $user->university_id));

            $activeSessions = \DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes($timeoutMinutes)->timestamp)
                ->count();

            // The current session is included in the count; only block when the
            // configured maximum has actually been exceeded.
            if ($activeSessions > $maxSessions) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'You have reached the maximum number of concurrent sessions. Please log out from another device.']);
            }
        }

        return $next($request);
    }
}
