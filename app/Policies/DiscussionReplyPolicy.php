<?php

namespace App\Policies;

use App\Models\DiscussionReply;
use App\Models\User;

class DiscussionReplyPolicy
{
    public function view(User $user, DiscussionReply $reply): bool
    {
        $discussion = $reply->discussion;

        if ($discussion->status === 'closed') {
            if ($user->isAdmin() || $user->isLecturer()) {
                return true;
            }

            return $discussion->user_id === $user->id;
        }

        return true;
    }

    public function create(User $user, $discussion): bool
    {
        if ($user->isStudent() || $user->isLecturer()) {
            $enrollment = $discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();

            return $enrollment !== null;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function update(User $user, DiscussionReply $reply): bool
    {
        if ($reply->user_id === $user->id) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function delete(User $user, DiscussionReply $reply): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($reply->user_id === $user->id) {
            return true;
        }

        if ($user->isLecturer()) {
            $enrollment = $reply->discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if ($enrollment) {
                return true;
            }
        }

        return false;
    }

    public function acceptAnswer(User $user, DiscussionReply $reply): bool
    {
        $discussion = $reply->discussion;

        if ($discussion->user_id === $user->id) {
            return true;
        }

        if ($user->isLecturer() || $user->isAdmin()) {
            $enrollment = $discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();

            return $enrollment !== null;
        }

        return false;
    }
}
