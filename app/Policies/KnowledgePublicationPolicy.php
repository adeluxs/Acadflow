<?php

namespace App\Policies;

use App\Models\KnowledgePublication;
use App\Models\User;

class KnowledgePublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, KnowledgePublication $publication): bool
    {
        if ($publication->creator_id === $user->id || $this->canModerate($user, $publication)) {
            return true;
        }

        if (! $publication->isPublished()) {
            return false;
        }

        $visible = $publication->visibility === 'public'
            || ($publication->visibility === 'institution' && $publication->university_id !== null && $user->university_id !== null && $publication->university_id === $user->university_id);

        if (! $visible) {
            return false;
        }

        // Viewing the publication record is separate from access to protected
        // body content and digital files. Entitlements are enforced by the
        // controller and secure media service so premium items can expose a
        // safe catalogue preview without leaking purchased content.
        return $publication->access_type !== 'institution'
            || ($publication->university_id !== null && $user->university_id !== null && $publication->university_id === $user->university_id);
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, KnowledgePublication $publication): bool
    {
        return ($publication->creator_id === $user->id && in_array($publication->status, ['draft', 'changes_requested', 'rejected'], true))
            || $this->canModerate($user, $publication);
    }

    public function submit(User $user, KnowledgePublication $publication): bool
    {
        return $publication->creator_id === $user->id
            && in_array($publication->status, ['draft', 'changes_requested', 'rejected'], true);
    }

    public function moderate(User $user, KnowledgePublication $publication): bool
    {
        return $this->canModerate($user, $publication);
    }

    public function delete(User $user, KnowledgePublication $publication): bool
    {
        return ($publication->creator_id === $user->id && $publication->status === 'draft')
            || $this->canModerate($user, $publication);
    }

    protected function canModerate(User $user, KnowledgePublication $publication): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isUniversityAdmin()) {
            return $user->university_id !== null
                && $publication->university_id !== null
                && $user->university_id === $publication->university_id;
        }

        return $user->isDepartmentAdmin()
            && $user->university_id !== null
            && $publication->university_id !== null
            && $user->university_id === $publication->university_id
            && $user->department_id === $publication->department_id;
    }
}
