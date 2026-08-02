<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()->role;

        if (! in_array($userRole, $roles)) {
            $adminRoles = ['super_admin', 'university_admin', 'department_admin'];
            if (! in_array($userRole, $adminRoles)) {
                abort(403, 'Unauthorized access.');
            }
        }

        return $next($request);
    }
}

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                if (! $user->hasPermission($permission)) {
                    abort(403, 'You do not have permission to perform this action.');
                }
            } else {
                try {
                    $perm = Permission::from($permission);
                    if (! $user->hasPermission($perm)) {
                        abort(403, 'You do not have permission to perform this action.');
                    }
                } catch (\ValueError $e) {
                    abort(500, 'Invalid permission: '.$permission);
                }
            }
        }

        return $next($request);
    }
}
