<?php

namespace App\Policies;

use App\Models\AcademicEvent;
use App\Models\User;

class AcademicEventPolicy
{
    public function viewAny(?User $user): bool { return true; }

    public function view(?User $user, AcademicEvent $event): bool
    {
        if ($event->visibility === 'public' && in_array($event->status, ['published','ongoing','completed'], true)) return true;
        if (! $user) return false;
        if ($user->isSuperAdmin() || $event->organizer_id === $user->id) return true;
        if ($event->coOrganizers()->whereKey($user->id)->exists()) return true;
        if ($event->visibility === 'institution') return $event->university_id !== null && $event->university_id === $user->university_id;
        if ($event->group_id) return $event->group?->members()->where('user_id',$user->id)->where('status','active')->exists() ?? false;
        if ($event->knowledge_community_id) return $event->community?->members()->where('user_id',$user->id)->where('status','active')->exists() ?? false;
        if ($event->invitations()->where('invitee_id',$user->id)->whereIn('status',['pending','accepted'])->where('expires_at','>',now())->exists()) return true;
        return $event->registrations()->where('user_id',$user->id)->whereNotIn('status',['cancelled','removed','rejected'])->exists();
    }

    public function create(User $user): bool { return $user->is_active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null; }

    public function update(User $user, AcademicEvent $event): bool
    {
        return $user->isSuperAdmin()
            || ($user->isAdmin() && $event->university_id !== null && $user->university_id !== null && $event->university_id === $user->university_id)
            || $event->organizer_id === $user->id
            || $event->coOrganizers()->whereKey($user->id)->wherePivotIn('role',['co_organizer','manager'])->exists();
    }

    public function delete(User $user, AcademicEvent $event): bool { return $this->update($user,$event); }
    public function manageAttendees(User $user, AcademicEvent $event): bool { return $this->update($user,$event); }
    public function register(User $user, AcademicEvent $event): bool { return $this->view($user,$event) && $event->organizer_id !== $user->id; }
}
