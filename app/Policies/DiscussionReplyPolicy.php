<?php

namespace App\Policies;

use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\User;

/**
 * Legacy reply policy retained for older reply records. New discussions use
 * the shared engagement service, but every action here still enforces the
 * parent course tenant/membership boundary.
 */
class DiscussionReplyPolicy
{
    public function view(User $user, DiscussionReply $reply): bool
    {
        $discussion = $reply->discussion;
        if (! $user->canAccessCourse($discussion->course)) {
            return false;
        }

        if ($discussion->status === 'closed') {
            return $discussion->user_id === $user->id
                || $this->teaches($user, $discussion)
                || $user->isAdmin();
        }

        return true;
    }

    public function create(User $user, Discussion $discussion): bool
    {
        if ($discussion->status === 'closed' || ! $user->canAccessCourse($discussion->course)) {
            return false;
        }

        if ($user->isStudent()) {
            return $discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->exists();
        }

        if ($user->isLecturer()) {
            return $this->teaches($user, $discussion);
        }

        return $user->isAdmin();
    }

    public function update(User $user, DiscussionReply $reply): bool
    {
        if (! $user->canAccessCourse($reply->discussion->course)) {
            return false;
        }

        return $reply->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, DiscussionReply $reply): bool
    {
        if (! $user->canAccessCourse($reply->discussion->course)) {
            return false;
        }

        return $reply->user_id === $user->id
            || $user->isAdmin()
            || $this->teaches($user, $reply->discussion);
    }

    public function acceptAnswer(User $user, DiscussionReply $reply): bool
    {
        $discussion = $reply->discussion;
        if (! $user->canAccessCourse($discussion->course)) {
            return false;
        }

        return $discussion->user_id === $user->id
            || $user->isAdmin()
            || $this->teaches($user, $discussion);
    }

    private function teaches(User $user, Discussion $discussion): bool
    {
        return $user->isLecturer()
            && $discussion->course->lecturerAssignments()
                ->where('user_id', $user->id)
                ->exists();
    }
}
