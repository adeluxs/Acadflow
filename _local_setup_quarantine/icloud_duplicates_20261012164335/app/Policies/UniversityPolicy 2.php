<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\University;
use App\Models\User;

class UniversityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_DEPARTMENTS);
    }

    public function view(User $user, University $university): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->university_id === $university->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_DEPARTMENT);
    }

    public function update(User $user, University $university): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, University $university): bool
    {
        return $user->hasPermission(Permission::DELETE_DEPARTMENT);
    }
}
