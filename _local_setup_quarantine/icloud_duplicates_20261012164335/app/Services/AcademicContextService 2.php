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
            ->whereHas('academicSession', fn ($query) => $query->where('university_id', $universityId))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function activeSemesterForUser(User $user): ?Semester
    {
        return $this->activeSemesterForUniversity($user->university_id);
    }

    public function activeSemesterForCourse(Course $course): ?Semester
    {
        $course->loadMissing('department.faculty');

        return $this->activeSemesterForUniversity($course->department?->faculty?->university_id);
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

        return (int) $semester->academicSession?->university_id === (int) $course->department?->faculty?->university_id;
    }
}
