<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_COURSES)
            || $user->hasPermission(Permission::VIEW_ENROLLED_COURSES);
    }

    public function view(User $user, Course $course): bool
    {
        return $user->canAccessCourse($course);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_COURSE);
    }

    public function createForCourse(User $user, $course): bool
    {
        // Only lecturers assigned to the course can create tasks
        if ($user->isLecturer() && $course->lecturerAssignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Department admins can create for courses in their department
        if ($user->isDepartmentAdmin() && $user->department_id === $course->department_id) {
            return true;
        }

        // University admins and super admins can always create
        return $user->isUniversityAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, Course $course): bool
    {
        if ($user->hasPermission(Permission::EDIT_COURSE)) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if ($user->isUniversityAdmin() && $user->university_id === $course->department->faculty->university_id) {
                return true;
            }

            if ($user->isDepartmentAdmin() && $user->department_id === $course->department_id) {
                return true;
            }

            if ($user->isLecturer() && $course->lecturerAssignments()->where('user_id', $user->id)->exists()) {
                return $user->hasPermission(Permission::EDIT_COURSE);
            }
        }

        return false;
    }

    public function delete(User $user, Course $course): bool
    {
        if (! $user->hasPermission(Permission::DELETE_COURSE)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isUniversityAdmin() || $user->isDepartmentAdmin()) {
            return $user->department_id === $course->department_id;
        }

        return false;
    }

    public function assignLecturer(User $user, Course $course): bool
    {
        return $user->hasPermission(Permission::ASSIGN_LECTURER)
            && $user->department_id === $course->department_id;
    }

    public function enroll(User $user, Course $course): bool
    {
        return $user->hasPermission(Permission::ENROLL_IN_COURSE)
            && $course->is_active;
    }

    public function addMaterial(User $user, Course $course): bool
    {
        // Super admins and university admins can add materials to any course
        if ($user->isSuperAdmin() || $user->isUniversityAdmin()) {
            return true;
        }

        // Department admins can add for courses in their department
        if ($user->isDepartmentAdmin() && $user->department_id === $course->department_id) {
            return true;
        }

        // Lecturers can add materials to courses they teach
        if ($user->isLecturer() && $course->lecturerAssignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function addDiscussion(User $user, Course $course): bool
    {
        // Any user who can access the course can create a discussion
        return $user->canAccessCourse($course);
    }
}
