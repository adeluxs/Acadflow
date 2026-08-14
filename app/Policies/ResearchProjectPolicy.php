<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;

class ResearchProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ResearchProject $project): bool
    {
        if (! $this->sameScope($user, $project)) {
            return false;
        }

        return $user->isAdmin()
            || $project->owner_id === $user->id
            || $project->supervisor_id === $user->id
            || $project->co_supervisor_id === $user->id
            || $project->memberRecords()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['student', 'lecturer', 'department_admin', 'university_admin', 'super_admin'], true);
    }

    public function update(User $user, ResearchProject $project): bool
    {
        if (! $this->view($user, $project) || in_array($project->status, ['archived'], true)) {
            return false;
        }

        return $user->isAdmin()
            || $project->owner_id === $user->id
            || $project->memberRecords()->where('user_id', $user->id)->whereJsonContains('permissions', 'write')->exists();
    }

    public function review(User $user, ResearchProject $project): bool
    {
        return $this->sameScope($user, $project)
            && ($user->isAdmin() || $project->supervisor_id === $user->id || $project->co_supervisor_id === $user->id);
    }

    public function transition(User $user, ResearchProject $project): bool
    {
        return $this->update($user, $project) || $this->review($user, $project);
    }

    public function validate(User $user, ResearchProject $project): bool
    {
        return $this->view($user, $project);
    }

    public function publish(User $user, ResearchProject $project): bool
    {
        return $this->view($user, $project)
            && ($user->isAdmin() || $project->owner_id === $user->id || $project->supervisor_id === $user->id)
            && in_array($project->status, ['approved', 'archived'], true)
            && $project->approved_at !== null;
    }

    protected function sameScope(User $user, ResearchProject $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->university_id !== $project->university_id) {
            return false;
        }

        return ! $user->isDepartmentAdmin() || $user->department_id === $project->department_id;
    }
}
