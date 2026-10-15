<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SubmissionTask;
use App\Models\User;

class SubmissionTaskPolicy
{
    /**
     * View all submission tasks for a course
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_COURSE_SUBMISSIONS);
    }

    /**
     * View a specific submission task
     */
    public function view(User $user, SubmissionTask $task): bool
    {
        // Super admin can view all
        if ($user->isSuperAdmin()) {
            return true;
        }

        // University admin can view tasks in their university
        if ($user->isUniversityAdmin() && $user->university_id === $task->course->department->faculty->university_id) {
            return true;
        }

        // Department admin can view tasks in their department
        if ($user->isDepartmentAdmin() && $user->department_id === $task->course->department_id) {
            return true;
        }

        // Lecturer can view tasks for their course
        if ($user->isLecturer() && $task->course->lecturerAssignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Students can view published, visible tasks for their enrolled courses
        if ($user->isStudent() && $task->is_visible_to_students && $task->status === 'published') {
            return $user->enrollments()
                ->where('course_id', $task->course_id)
                ->where('semester_id', $task->semester_id)
                ->where('status', 'enrolled')
                ->exists();
        }

        return false;
    }

    /**
     * Create a new submission task
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_COURSE)
            || $user->hasPermission(Permission::EDIT_COURSE);
    }

    /**
     * Update a submission task
     */
    public function update(User $user, SubmissionTask $task): bool
    {
        // Can only edit draft and published tasks (not closed or archived)
        if (in_array($task->status, ['closed', 'archived'])) {
            return false;
        }

        // Creator can edit only while they still have legitimate course access.
        if ($task->created_by === $user->id && $user->canAccessCourse($task->course)) {
            return true;
        }

        // Department admin can edit tasks in their department
        if ($user->isDepartmentAdmin() && $user->department_id === $task->course->department_id) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isUniversityAdmin()
            && $user->university_id === $task->course->department->faculty->university_id;
    }

    /**
     * Publish a task (make it visible and open for submissions)
     */
    public function publish(User $user, SubmissionTask $task): bool
    {
        // Only draft tasks can be published
        if ($task->status !== 'draft') {
            return false;
        }

        return $this->update($user, $task);
    }

    /**
     * Close a task (stop accepting new submissions)
     */
    public function close(User $user, SubmissionTask $task): bool
    {
        // Can close published tasks only
        if (! in_array($task->status, ['published'])) {
            return false;
        }

        return $this->update($user, $task);
    }

    /**
     * Delete a submission task
     */
    public function delete(User $user, SubmissionTask $task): bool
    {
        // Only draft tasks can be deleted
        if ($task->status !== 'draft') {
            return false;
        }

        return $this->update($user, $task);
    }

    /**
     * Grant deadline extension to a student
     */
    public function grantExtension(User $user, SubmissionTask $task): bool
    {
        // Creator can grant extensions only while still authorized for the course.
        if ($task->created_by === $user->id && $user->canAccessCourse($task->course)) {
            return true;
        }

        // Lecturers assigned to course can grant extensions
        if ($user->isLecturer() && $task->course->lecturerAssignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isUniversityAdmin()
            && $user->university_id === $task->course->department->faculty->university_id;
    }

    /**
     * View submission analytics for a task
     */
    public function viewAnalytics(User $user, SubmissionTask $task): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_ANALYTICS)
            && $this->update($user, $task);
    }
}
