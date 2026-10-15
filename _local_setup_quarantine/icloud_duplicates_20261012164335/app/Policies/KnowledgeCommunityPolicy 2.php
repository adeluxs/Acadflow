<?php

namespace App\Policies;

use App\Models\KnowledgeCommunity;
use App\Models\User;

class KnowledgeCommunityPolicy
{
    public function viewAny(?User $user): bool { return true; }

    public function view(?User $user, KnowledgeCommunity $community): bool
    {
        if ($community->visibility === 'public' && $community->status === 'active') return true;
        if (! $user) return false;
        if ($user->isSuperAdmin() || $community->owner_id === $user->id) return true;
        if ($community->visibility === 'institution' && $community->university_id !== null && $community->university_id === $user->university_id) return true;
        return $community->members()->where('user_id',$user->id)->where('status','active')->exists();
    }

    public function create(User $user): bool { return $user->is_active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null; }
    public function update(User $user, KnowledgeCommunity $community): bool { return $user->isSuperAdmin() || ($user->isAdmin() && $community->university_id !== null && $user->university_id !== null && $community->university_id === $user->university_id) || $community->owner_id === $user->id || $community->members()->where('user_id',$user->id)->where('status','active')->whereIn('role',['owner','administrator'])->exists(); }
    public function delete(User $user, KnowledgeCommunity $community): bool { return $user->isSuperAdmin() || $community->owner_id === $user->id; }
    public function moderate(User $user, KnowledgeCommunity $community): bool { return $this->update($user,$community) || $community->members()->where('user_id',$user->id)->where('status','active')->whereIn('role',['moderator'])->exists(); }
    public function post(User $user, KnowledgeCommunity $community): bool { return $this->view($user,$community) && ($user->isAdmin() || $community->members()->where('user_id',$user->id)->where('status','active')->exists()); }
}
