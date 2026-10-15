<?php

declare(strict_types=1);

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
        return $user->hasPermission(Permission::VIEW_COURSE_ATTENDANCE)
            && $user->canAccessCourse($session->course);
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
            && $session->status === 'active'
            && $user->enrollments()
                ->where('course_id', $session->course_id)
                ->where('status', 'enrolled')
                ->exists();
    }

    public function edit(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::EDIT_ATTENDANCE)
            && $session->lecturer_id === $user->id
            && $session->status === 'active';
    }

    public function export(User $user, AttendanceSession $session): bool
    {
        return $user->hasPermission(Permission::EXPORT_ATTENDANCE)
            && $user->canAccessCourse($session->course);
    }
}
