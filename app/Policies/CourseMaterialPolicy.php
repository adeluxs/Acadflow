<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    public function view(User $user, CourseMaterial $material): bool
    {
        if ($material->is_public) return true;
        if ($material->uploaded_by === $user->id) return true;

        $course = $material->course;
        if (! $user->canAccessCourse($course)) return false;

        if ($user->isStudent()) return (bool) $material->is_visible;
        return $user->isAdmin() || $user->isLecturer();
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
