<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionTimeout
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = SettingService::getSessionTimeout();
            $lastActivity = Session::get('last_activity_time');
            
            if ($lastActivity && now()->diffInMinutes($lastActivity) > $timeoutMinutes) {
                Auth::logout();
                Session::flush();
                
                return redirect()->route('login')
                    ->withErrors(['session' => 'Your session has expired. Please log in again.']);
            }
            
            Session::put('last_activity_time', now());
        }

        return $next($request);
    }
}
