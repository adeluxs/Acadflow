<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AcademicContextService
{
    public function activeSemesterForUniversity(?int $universityId): ?Semester
    {
        if (! $universityId) {
            return null;
        }

        return Semester::query()
            ->with('academicSession')
            ->where('is_active', true)
            ->whereHas('academicSession', fn ($query) => $query->where('university_id', $universityId)->whereNull('department_id'))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function activeSemesterForUser(User $user): ?Semester
    {
        if ($user->department_id && $semester = $this->activeSemesterForDepartment((int) $user->department_id)) {
            return $semester;
        }

        return $this->activeSemesterForUniversity($user->university_id);
    }

    public function activeSemesterForCourse(Course $course): ?Semester
    {
        $course->loadMissing('department.faculty');

        return $this->activeSemesterForDepartment((int) $course->department_id)
            ?? $this->activeSemesterForUniversity($course->department?->faculty?->university_id);
    }

    public function activeSemesterForDepartment(int $departmentId): ?Semester
    {
        return Semester::with('academicSession')->where('is_active', true)->whereHas('academicSession', fn ($q) => $q->where('department_id', $departmentId))->orderByDesc('start_date')->orderByDesc('id')->first();
    }

    public function requireActiveSemesterForUser(User $user): Semester
    {
        return $this->activeSemesterForUser($user)
            ?? throw (new ModelNotFoundException)->setModel(Semester::class);
    }

    public function requireActiveSemesterForCourse(Course $course): Semester
    {
        return $this->activeSemesterForCourse($course)
            ?? throw (new ModelNotFoundException)->setModel(Semester::class);
    }

    public function semesterBelongsToCourse(Semester $semester, Course $course): bool
    {
        $semester->loadMissing('academicSession');
        $course->loadMissing('department.faculty');

        return (int) $semester->academicSession?->university_id === (int) $course->department?->faculty?->university_id
            && (! $semester->academicSession?->department_id || (int) $semester->academicSession->department_id === (int) $course->department_id);
    }
}
