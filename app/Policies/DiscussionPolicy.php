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

        // Public discussions or open status visible to enrolled users
        if ($discussion->status === 'open' || $discussion->status === 'resolved') {
            // Admin can view all
            if ($user->isAdmin()) {
                return true;
            }

            // Owner can view
            if ($discussion->user_id === $user->id) {
                return true;
            }

            // Lecturer of the course can view
            if ($user->isLecturer()) {
                $assignment = $course->lecturerAssignments()
                    ->where('user_id', $user->id)
                    ->first();
                if ($assignment){
                    return true;
                }
            }

            // Student must be enrolled
            if ($user->isStudent()) {
                $enrollment = $course->enrollments()
                    ->where('user_id', $user->id)
                    ->where('status', 'enrolled')
                    ->first();

                return $enrollment !== null;
            }
        }

        // Only admin/lecturer can view closed discussions
        if ($discussion->status === 'closed') {
            if ($user->isAdmin() || $user->isLecturer()) {
                return true;
            }
            if ($discussion->user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user, Course $course): bool
    {
        // Only enrolled students and lecturers can create discussions
        if ($user->isStudent() || $user->isLecturer()) {
            $enrollment = $course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();

            return $enrollment !== null;
        }

        // Admin can create in any course
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function update(User $user, Discussion $discussion): bool
    {
        // Owner can update
        if ($discussion->user_id === $user->id) {
            return true;
        }

        // Admin can update all
        if ($user->isAdmin()) {
            return true;
        }

        // Lecturer of the course can update
        if ($user->isLecturer()) {
            $enrollment = $discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if ($enrollment) {
                return true;
            }
        }

        return false;
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        // Admin can delete all
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can delete
        if ($discussion->user_id === $user->id) {
            return true;
        }

        // Lecturer of the course can delete
        if ($user->isLecturer()) {
            $enrollment = $discussion->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if ($enrollment) {
                return true;
            }
        }

        return false;
    }

    public function pin(User $user, Discussion $discussion): bool
    {
        return $user->isLecturer() || $user->isAdmin();
    }

    public function close(User $user, Discussion $discussion): bool
    {
        return $user->isLecturer() || $user->isAdmin();
    }
}
