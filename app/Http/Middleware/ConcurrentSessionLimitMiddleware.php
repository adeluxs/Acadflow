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

        if ($user) {
            $maxSessions = (int) SettingService::get('max_concurrent_sessions', 3);
            $activeSessions = \DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes(30)->timestamp)
                ->count();

            if ($activeSessions >= $maxSessions) {
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
