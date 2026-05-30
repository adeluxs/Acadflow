<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_USERS);
    }

    public function view(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return $user->hasPermission(Permission::VIEW_OWN_PROFILE);
        }

        if ($user->isDepartmentAdmin()) {
            return $targetUser->department_id === $user->department_id;
        }

        return $user->hasPermission(Permission::VIEW_ALL_USERS);
    }

    public function create(User $user): bool
    {
        if (! $user->hasPermission(Permission::CREATE_USERS)) {
            return false;
        }

        return true;
    }

    public function canInviteRole(User $user, string $role): bool
    {
        if (! $user->hasPermission(Permission::CREATE_USERS)) {
            return false;
        }

        if ($user->isDepartmentAdmin()) {
            return in_array($role, ['lecturer', 'student']);
        }

        if ($user->isUniversityAdmin()) {
            return in_array($role, ['department_admin', 'lecturer', 'student']);
        }

        if ($user->isSuperAdmin()) {
            return in_array($role, ['university_admin', 'department_admin', 'lecturer', 'student']);
        }

        return false;
    }

    public function update(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return $user->hasPermission(Permission::EDIT_OWN_PROFILE);
        }

        if ($user->isDepartmentAdmin()) {
            return $targetUser->department_id === $user->department_id
                && $targetUser->role !== 'department_admin'
                && $targetUser->role !== 'university_admin';
        }

        return $user->hasPermission(Permission::EDIT_USERS);
    }

    public function delete(User $user, User $targetUser): bool
    {
        if (! $user->hasPermission(Permission::DELETE_USERS)) {
            return false;
        }

        return $user->id !== $targetUser->id;
    }

    public function manageRoles(User $user): bool
    {
        return $user->hasPermission(Permission::MANAGE_ROLES);
    }
}
