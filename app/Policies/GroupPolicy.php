<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool { return $user->is_active; }

    public function view(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin() || $group->leader_id === $user->id) return true;
        if ($group->visibility === 'public' && $group->status !== 'archived') return true;
        if ($group->visibility === 'institution' && $group->university_id !== null && $group->university_id === $user->university_id) return true;
        return $group->members()->where('user_id',$user->id)->where('status','active')->exists();
    }

    public function create(User $user): bool { return $user->is_active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null; }
    public function update(User $user, Group $group): bool { return $user->isSuperAdmin() || ($user->isAdmin() && $group->university_id !== null && $user->university_id !== null && $group->university_id === $user->university_id) || $group->leader_id === $user->id || $group->members()->where('user_id',$user->id)->where('status','active')->whereIn('role',['leader','administrator'])->exists(); }
    public function delete(User $user, Group $group): bool { return $user->isSuperAdmin() || $group->leader_id === $user->id; }
    public function manageMembers(User $user, Group $group): bool { return $this->update($user,$group); }
    public function join(User $user, Group $group): bool { return $this->view($user,$group) && ! $group->members()->where('user_id',$user->id)->where('status','active')->exists(); }
}
