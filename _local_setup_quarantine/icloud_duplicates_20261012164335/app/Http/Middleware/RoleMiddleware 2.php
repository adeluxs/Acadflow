<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Preserve administrative hierarchy without allowing administrators to
        // impersonate student or lecturer-only workflows.
        $requestedAdminRole = count(array_intersect($roles, [
            'super_admin',
            'university_admin',
            'department_admin',
        ])) > 0;

        if ($requestedAdminRole && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($requestedAdminRole && $user->isUniversityAdmin() && in_array('department_admin', $roles, true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized access.');
    }
}
