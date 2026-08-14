<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            try {
                $resolved = $permission instanceof Permission
                    ? $permission
                    : Permission::from($permission);
            } catch (\ValueError) {
                abort(500, 'Invalid permission: '.$permission);
            }

            if (! $user->hasPermission($resolved)) {
                abort(403, 'You do not have permission to perform this action.');
            }
        }

        return $next($request);
    }
}
