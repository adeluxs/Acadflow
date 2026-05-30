<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow super admins to bypass maintenance mode
        if (SettingService::isMaintenanceMode() && ! ($request->user() && $request->user()->isSuperAdmin())) {
            // Allow access to login/logout routes
            if (! $request->is('login', 'logout', 'api/*')) {
                return response()->view('errors.maintenance', [], 503);
            }
        }

        return $next($request);
    }
}
