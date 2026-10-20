<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResearchType;
use App\Models\Semester;
use App\Models\University;
use Illuminate\Support\Facades\DB;

/**
 * Provides an internal academic context for independent users without attaching
 * that synthetic institution to the user's profile. Existing semester-dependent
 * course/research code can therefore remain intact.
 */
class IndependentAcademicContextService
{
    public const UNIVERSITY_CODE = 'ACADFIND';
    public const FACULTY_CODE = 'ACADF-GEN';
    public const DEPARTMENT_CODE = 'ACADF-IND';

    /** @return array{university:University,faculty:Faculty,department:Department,session:AcademicSession,semester:Semester} */
    public function context(): array
    {
        return DB::transaction(function (): array {
            $university = University::query()->firstOrCreate(
                ['code' => self::UNIVERSITY_CODE],
                [
                    'name' => 'AcadSyntra Independent Workspace',
                    'short_name' => 'Independent',
                    'email' => null,
                    'timezone' => (string) config('app.timezone', 'Africa/Lagos'),
                    'is_active' => true,
                    'settings' => ['system_managed' => true, 'hidden_from_affiliation' => true],
                ]
            );

            $faculty = Faculty::query()->firstOrCreate(
                ['code' => self::FACULTY_CODE],
                [
                    'university_id' => $university->id,
                    'name' => 'Independent Academic Workspaces',
                    'short_name' => 'Independent',
                    'is_active' => true,
                    'catalog_source' => 'system',
                    'is_catalog_template' => false,
                ]
            );

            $department = Department::query()->firstOrCreate(
                ['code' => self::DEPARTMENT_CODE],
                [
                    'faculty_id' => $faculty->id,
                    'name' => 'Independent Classrooms & Research',
                    'short_name' => 'Independent',
                    'is_active' => true,
                    'catalog_source' => 'system',
                    'is_catalog_template' => false,
                    'adoption_status' => 'independent_active',
                ]
            );

            $year = (int) now()->format('Y');
            $session = AcademicSession::query()->firstOrCreate(
                ['university_id' => $university->id, 'name' => 'Independent '.$year],
                [
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->endOfYear()->toDateString(),
                    'is_current' => true,
                    'is_active' => true,
                ]
            );

            AcademicSession::query()
                ->where('university_id', $university->id)
                ->whereKeyNot($session->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $semester = Semester::query()->firstOrCreate(
                ['academic_session_id' => $session->id, 'name' => 'Independent'],
                [
                    'number' => 1,
                    'start_date' => $session->start_date,
                    'end_date' => $session->end_date,
                    'grading_deadline' => $session->end_date,
                    'is_active' => true,
                ]
            );

            return compact('university', 'faculty', 'department', 'session', 'semester');
        });
    }

    public function ensureResearchType(): ResearchType
    {
        return ResearchType::query()->firstOrCreate(
            ['university_id' => null, 'slug' => 'independent-research'],
            [
                'name' => 'Independent Research Project',
                'description' => 'Flexible personal or collaborative research workspace for independent AcadSyntra users.',
                'template_schema' => null,
                'validation_rules' => null,
                'similarity_threshold' => 20,
                'publication_eligible' => true,
                'is_active' => true,
            ]
        );
    }
}
