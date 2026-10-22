<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Course;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Small, freshly authorised catalogues for management forms. */
class AdminLookupService
{
    public const KINDS = ['departments', 'universities', 'commercial_universities', 'deans', 'audit_users', 'lecturers', 'semesters'];

    public function query(User $actor, string $kind, array $filters = []): Builder
    {
        abort_unless($actor->isAdmin(), 403);
        $departmentId = (int) ($filters['department_id'] ?? 0);
        $universityId = $actor->isSuperAdmin() ? (int) ($filters['university_id'] ?? 0) : (int) $actor->university_id;
        $onlyActive = empty($filters['include_inactive']);
        $departments = fn () => app(AcademicOrganisationService::class)->departmentIds($actor)->select('departments.id');
        $courseId = (int) ($filters['course_id'] ?? 0);
        if ($kind === 'lecturers' && $courseId) {
            $course = Course::with('department.faculty')->findOrFail($courseId);
            abort_unless(app(AcademicOrganisationService::class)->canManageDepartment($actor, $course->department), 403);
            $departmentId = (int) $course->department_id;
        }

        $query = match ($kind) {
            'departments' => app(AcademicOrganisationService::class)->departmentIds($actor)
                ->select(['id', 'name', 'faculty_id'])->when($onlyActive, fn ($q) => $q->where('is_active', true))
                ->with('faculty:id,university_id,short_name', 'faculty.university:id,short_name')->orderBy('name')->orderBy('id'),
            'universities', 'commercial_universities' => University::query()->select(['id', 'name', 'short_name', 'code'])
                ->where('is_active', true)->orderBy('name')->orderBy('id'),
            'deans' => User::query()->select(['id', 'first_name', 'last_name', 'email'])
                ->whereIn('role', ['lecturer', 'university_admin'])->when($onlyActive, fn ($q) => $q->where('is_active', true))
                ->where('university_id', $universityId ?: 0)->orderBy('first_name')->orderBy('id'),
            'audit_users' => User::query()->select(['id', 'first_name', 'last_name', 'email'])
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('audit_logs')->whereColumn('audit_logs.user_id', 'users.id'))
                ->orderBy('first_name')->orderBy('id'),
            'lecturers' => User::query()->select(['id', 'first_name', 'last_name', 'email'])
                ->whereHas('institutionAffiliations', fn ($q) => $q->approved()->where('affiliation_type', 'lecturer')
                    ->where('department_id', $departmentId)->whereIn('department_id', $departments()))
                ->orderBy('first_name')->orderBy('id'),
            'semesters' => Semester::query()->select(['id', 'name', 'academic_session_id'])
                ->with('academicSession:id,name')->whereHas('academicSession', fn ($q) => $q
                    ->whereIn('university_id', Department::query()->select('faculties.university_id')->join('faculties', 'faculties.id', '=', 'departments.faculty_id')
                        ->where('departments.id', $departmentId)->whereIn('departments.id', $departments()))
                    ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $departmentId)))
                ->orderByDesc('id'),
            default => abort(404),
        };

        if (in_array($kind, ['universities', 'commercial_universities', 'audit_users'], true)) abort_unless($actor->isSuperAdmin(), 403);
        if ($kind === 'deans') abort_unless($actor->isSuperAdmin() || $actor->isUniversityAdmin(), 403);
        if ($kind === 'lecturers' && $courseId) $query->whereNotIn('id', LecturerCourseAssignment::select('user_id')->where('course_id', $courseId));
        if ($kind === 'commercial_universities') $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('commercial_accounts')
            ->whereColumn('commercial_accounts.university_id', 'universities.id')->whereNull('commercial_accounts.department_id'));

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $columns = match ($kind) {
                'departments', 'semesters' => ['name'],
                'universities', 'commercial_universities' => ['name', 'short_name', 'code'],
                default => ['first_name', 'last_name', 'email'],
            };
            $query->where(function ($q) use ($columns, $search): void {
                foreach ($columns as $column) $q->orWhere($column, 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    public function option(Model $model): array
    {
        $label = match (true) {
            $model instanceof Department => $model->name.' · '.$model->faculty?->university?->short_name.' / '.$model->faculty?->short_name,
            $model instanceof User => $model->full_name.' · '.$model->email,
            $model instanceof Semester => $model->name.' · '.$model->academicSession?->name,
            default => $model->name,
        };

        return ['id' => $model->id, 'label' => $label];
    }

    public function selected(User $actor, string $kind, mixed $id, array $filters = [], bool $includeInactive = false): ?array
    {
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) return null;
        $model = $this->query($actor, $kind, $filters + ['include_inactive' => $includeInactive])->find((int) $id);

        return $model ? $this->option($model) : null;
    }
}
