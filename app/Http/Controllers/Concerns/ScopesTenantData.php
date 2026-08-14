<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesTenantData
{
    protected function scopeCourseQuery(Builder $query, User $user, string $relation = ''): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $departmentRelation = $relation !== '' ? $relation.'.department' : 'department';
        $query->whereHas($departmentRelation.'.faculty', fn (Builder $scope) => $scope->where('university_id', $user->university_id));

        if ($user->isDepartmentAdmin()) {
            $query->whereHas($departmentRelation, fn (Builder $scope) => $scope->whereKey($user->department_id));
        }

        return $query;
    }

    protected function assertCourseTenant(User $user, Course $course): void
    {
        $course->loadMissing('department.faculty');
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($course->department?->faculty?->university_id === $user->university_id, 404);
        if ($user->isDepartmentAdmin()) {
            abort_unless($course->department_id === $user->department_id, 404);
        }
    }

    protected function scopeInvoiceQuery(Builder $query, User $user, string $relation = ''): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $path = $relation !== '' ? $relation.'.semester.academicSession' : 'semester.academicSession';
        return $query->whereHas($path, fn (Builder $scope) => $scope->where('university_id', $user->university_id));
    }
}
