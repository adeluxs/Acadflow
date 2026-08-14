<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    public function view(User $user, Discussion $discussion): bool
    {
        $course = $discussion->course;
        if (! $user->canAccessCourse($course)) return false;

        if (in_array($discussion->status, ['open', 'resolved'], true)) return true;
        if ($discussion->status === 'closed') {
            return $discussion->user_id === $user->id || $user->isAdmin() || $this->teaches($user, $course);
        }

        return $discussion->user_id === $user->id || $user->isAdmin() || $this->teaches($user, $course);
    }

    public function create(User $user, Course $course): bool
    {
        if ($user->isStudent()) {
            return $course->enrollments()->where('user_id', $user->id)->where('status', 'enrolled')->exists();
        }
        if ($user->isLecturer()) return $this->teaches($user, $course);
        return $user->isAdmin() && $user->canAccessCourse($course);
    }

    public function update(User $user, Discussion $discussion): bool
    {
        if (! $user->canAccessCourse($discussion->course)) return false;
        if ($discussion->user_id === $user->id) return true;
        return $user->isAdmin() || $this->teaches($user, $discussion->course);
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $this->update($user, $discussion);
    }

    public function pin(User $user, Discussion $discussion): bool
    {
        return $user->canAccessCourse($discussion->course)
            && ($user->isAdmin() || $this->teaches($user, $discussion->course));
    }

    public function close(User $user, Discussion $discussion): bool
    {
        return $this->pin($user, $discussion);
    }

    private function teaches(User $user, Course $course): bool
    {
        return $user->isLecturer() && $course->lecturerAssignments()->where('user_id', $user->id)->exists();
    }
}
