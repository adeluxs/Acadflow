<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Faculty;
use App\Models\User;

class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_DEPARTMENTS);
    }

    public function view(User $user, Faculty $faculty): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->university_id === $faculty->university_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_FACULTY);
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission(Permission::EDIT_FACULTY)
                && $user->university_id !== null
                && $user->university_id === $faculty->university_id);
    }

    public function delete(User $user, Faculty $faculty): bool
    {
        return $user->hasPermission(Permission::DELETE_FACULTY)
            && $user->isSuperAdmin();
    }
}
