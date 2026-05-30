<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AttendanceSession;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_OWN_ATTENDANCE)
            || $user->hasPermission(Permission::VIEW_COURSE_ATTENDANCE);
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        if ($user->hasPermission(Permission::VIEW_COURSE_ATTENDANCE)) {
            return $user->canViewCourseSubmissions($session->course);
        }

        return $user->hasPermission(Permission::VIEW_OWN_ATTENDANCE);
    }

    public function start(User $user): bool
    {
        return $user->hasPermission(Permission::START_ATTENDANCE);
    }

    public function stop(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::STOP_ATTENDANCE)
            && $session->lecturer_id === $user->id
            && $session->status === 'active';
    }

    public function checkIn(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::CHECK_IN)
            && $session->status === 'active';
    }

    public function edit(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::EDIT_ATTENDANCE)
            && $user->canViewCourseSubmissions($session->course);
    }

    public function export(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::EXPORT_ATTENDANCE)
            && $user->canViewCourseSubmissions($session->course);
    }
}
