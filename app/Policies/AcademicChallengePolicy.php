<?php

namespace App\Policies;

use App\Models\AcademicChallenge;
use App\Models\User;

class AcademicChallengePolicy
{
    public function viewAny(?User $user): bool { return true; }

    public function view(?User $user, AcademicChallenge $challenge): bool
    {
        if ($challenge->visibility === 'public' && in_array($challenge->status,['published','active','judging','completed'],true)) return true;
        if (! $user) return false;
        if ($user->isSuperAdmin() || $challenge->organizer_id === $user->id) return true;
        if ($challenge->judges()->whereKey($user->id)->wherePivot('status','active')->exists()) return true;
        if ($challenge->visibility === 'institution') return $challenge->university_id !== null && $challenge->university_id === $user->university_id;
        if ($challenge->group_id) return $challenge->group?->members()->where('user_id',$user->id)->where('status','active')->exists() ?? false;
        if ($challenge->knowledge_community_id) return $challenge->community?->members()->where('user_id',$user->id)->where('status','active')->exists() ?? false;
        return $challenge->entries()->where('user_id',$user->id)->exists();
    }

    public function create(User $user): bool { return $user->is_active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null; }
    public function update(User $user, AcademicChallenge $challenge): bool { return $user->isSuperAdmin() || ($user->isAdmin() && $challenge->university_id !== null && $user->university_id !== null && $challenge->university_id === $user->university_id) || $challenge->organizer_id === $user->id; }
    public function delete(User $user, AcademicChallenge $challenge): bool { return $this->update($user,$challenge); }
    public function submit(User $user, AcademicChallenge $challenge): bool { return $this->view($user,$challenge) && $challenge->organizer_id !== $user->id; }
    public function judge(User $user, AcademicChallenge $challenge): bool { return $this->update($user,$challenge) || $challenge->judges()->whereKey($user->id)->wherePivot('status','active')->exists(); }
}
