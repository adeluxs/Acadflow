<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\User;

/**
 * Single source of truth for course membership scope (institution + department).
 *
 * Every join/enroll/invite path delegates here so the rule cannot drift between
 * CourseController, EnrollmentController and CourseMembershipController.
 *
 * Design notes:
 *  - Independent classrooms have no institutional scope and are always allowed.
 *  - Comparisons are cast to int because database drivers may return integer
 *    columns as strings; a strict === comparison would wrongly deny access.
 *  - The department rule only blocks an explicit mismatch. A student who has no
 *    department on their profile cannot be a mismatch, so they are judged on the
 *    institution rule alone.
 */
class CourseMembershipScope
{
    public static function denial(User $user, Course $course, bool $requireDepartment): ?string
    {
        if ($course->isIndependent()) {
            return null;
        }

        $course->loadMissing('department.faculty');
        $courseUniversityId = $course->department?->faculty?->university_id;

        if (! $user->university_id || ! $courseUniversityId || (int) $user->university_id !== (int) $courseUniversityId) {
            return 'This course belongs to another institution. You can only join or enroll in courses from your own institution.';
        }

        if (
            $requireDepartment
            && $user->department_id
            && $course->department_id
            && (int) $user->department_id !== (int) $course->department_id
        ) {
            return 'You can only join or enroll in courses within your department.';
        }

        return null;
    }
}
