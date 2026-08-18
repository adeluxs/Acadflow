<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    public function view(User $user, CourseMaterial $material): bool
    {
        $course = $material->course;

        // Owners and authorized teaching/admin staff must always be able to
        // inspect materials they manage, including hidden/draft materials.
        if ($material->uploaded_by === $user->id) return true;
        if ($user->canAccessCourse($course) && ($user->isAdmin() || $user->isLecturer())) return true;

        // Hidden materials are never exposed to students/public viewers.
        if (! $material->is_visible) return false;
        if ($material->is_public) return true;
        if (! $user->canAccessCourse($course)) return false;

        if ($user->isStudent()) {
            if (! $material->requires_enrollment) return true;
            return $user->enrollments()
                ->where('course_id', $course->id)
                ->where('status', 'enrolled')
                ->exists();
        }

        return false;
    }

    public function create(User $user, Course $course): bool
    {
        if (! $user->canAccessCourse($course)) return false;
        return $user->isAdmin() || ($user->isLecturer() && $course->lecturerAssignments()->where('user_id', $user->id)->exists());
    }

    public function update(User $user, CourseMaterial $material): bool
    {
        if ($material->uploaded_by === $user->id) return $user->canAccessCourse($material->course);
        return $user->isAdmin() && $user->canAccessCourse($material->course);
    }

    public function delete(User $user, CourseMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
