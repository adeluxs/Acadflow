<?php

namespace App\Policies;

use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    public function view(User $user, CourseMaterial $material): bool
    {
        // Public materials can be viewed by anyone
        if ($material->is_public) {
            return true;
        }

        // Uploader can always view
        if ($material->uploaded_by === $user->id) {
            return true;
        }

        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Lecturer of the course can view
        if ($user->isLecturer()) {
            $enrollment = $material->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if ($enrollment) {
                return true;
            }
        }

        // Student must be enrolled in course and material must be visible
        if ($user->isStudent()) {
            $enrollment = $material->course->enrollments()
                ->where('user_id', $user->id)
                ->where('status', 'enrolled')
                ->first();
            if ($enrollment && $material->is_visible) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user, $course): bool
    {
        return $user->isLecturer() || $user->isAdmin();
    }

    public function update(User $user, CourseMaterial $material): bool
    {
        // Owner can update
        if ($material->uploaded_by === $user->id) {
            return true;
        }

        // Admin can update all
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function delete(User $user, CourseMaterial $material): bool
    {
        // Owner can delete
        if ($material->uploaded_by === $user->id) {
            return true;
        }

        // Admin can delete all
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }
}
