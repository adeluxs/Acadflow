<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GroupMember;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_OWN_SUBMISSIONS)
            || $user->hasPermission(Permission::VIEW_COURSE_SUBMISSIONS);
    }

    public function view(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->user_id) {
            return $user->hasPermission(Permission::VIEW_OWN_SUBMISSIONS);
        }

        if ($submission->group_id && GroupMember::where('group_id', $submission->group_id)
            ->where('user_id', $user->id)
            ->exists()) {
            return true;
        }

        return $user->hasPermission(Permission::VIEW_COURSE_SUBMISSIONS)
            && $user->canViewCourseSubmissions($submission->course);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_SUBMISSION);
    }

    public function update(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->user_id) {
            return in_array($submission->status, ['draft', 'correction_requested']);
        }

        if ($submission->group_id && GroupMember::where('group_id', $submission->group_id)
            ->where('user_id', $user->id)
            ->exists()) {
            return in_array($submission->status, ['draft', 'correction_requested']);
        }

        return false;
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $user->id === $submission->user_id
            && $submission->status === 'draft';
    }

    public function comment(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->user_id) {
            return $user->hasPermission(Permission::COMMENT_ON_SUBMISSION);
        }

        return $user->hasPermission(Permission::COMMENT_ON_SUBMISSION)
            && $user->canViewCourseSubmissions($submission->course);
    }

    public function grade(User $user, Submission $submission): bool
    {
        return $user->canGradeSubmission($submission);
    }

    public function approve(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::APPROVE_SUBMISSION)
            && $user->canViewCourseSubmissions($submission->course);
    }

    public function requestCorrection(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::REQUEST_CORRECTION)
            && $user->canViewCourseSubmissions($submission->course);
    }

    public function resubmit(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->user_id && $submission->status === 'correction_requested') {
            return $user->hasPermission(Permission::RESUBMIT);
        }

        if ($submission->group_id && $submission->status === 'correction_requested') {
            return GroupMember::where('group_id', $submission->group_id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function exportGrades(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::EXPORT_GRADES)
            && $user->canViewCourseSubmissions($submission->course);
    }
}
