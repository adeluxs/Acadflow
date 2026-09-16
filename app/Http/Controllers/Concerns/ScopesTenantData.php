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

        $courseScope = function (Builder $course) use ($user): void {
            $course->where(function (Builder $available) use ($user): void {
                if ($user->university_id) {
                    $available->where(function (Builder $institutional) use ($user): void {
                        $institutional->where('access_mode', 'institutional')
                            ->whereHas('department.faculty', fn (Builder $faculty) => $faculty->where('university_id', $user->university_id));
                        if ($user->isDepartmentAdmin()) {
                            $institutional->where('department_id', $user->department_id);
                        }
                    });
                } else {
                    $available->whereRaw('1 = 0');
                }

                $available->orWhere(function (Builder $independent) use ($user): void {
                    $independent->where('access_mode', 'independent')
                        ->where(function (Builder $participant) use ($user): void {
                            $participant->where('owner_user_id', $user->id)
                                ->orWhereHas('lecturerAssignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id))
                                ->orWhereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('user_id', $user->id)->where('status', 'enrolled'));
                        });
                });
            });
        };

        if ($relation === '') {
            $courseScope($query);
            return $query;
        }

        return $query->whereHas($relation, $courseScope);
    }

    protected function assertCourseTenant(User $user, Course $course): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($course->isIndependent()) {
            abort_unless($user->canAccessCourse($course), 404);
            return;
        }

        $course->loadMissing('department.faculty');
        abort_unless($user->university_id && $course->department?->faculty?->university_id === $user->university_id, 404);
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
