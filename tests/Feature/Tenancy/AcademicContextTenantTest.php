<?php

namespace Tests\Feature\Tenancy;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\University;
use App\Services\AcademicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicContextTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_semester_is_resolved_inside_the_course_university(): void
    {
        [$firstUniversity, $firstCourse] = $this->makeUniversityCourse('First');
        [$secondUniversity, $secondCourse] = $this->makeUniversityCourse('Second');

        $firstSession = AcademicSession::factory()->create(['university_id' => $firstUniversity->id, 'is_active' => true, 'is_current' => true]);
        $secondSession = AcademicSession::factory()->create(['university_id' => $secondUniversity->id, 'is_active' => true, 'is_current' => true]);
        $firstSemester = Semester::factory()->create(['academic_session_id' => $firstSession->id, 'is_active' => true]);
        $secondSemester = Semester::factory()->create(['academic_session_id' => $secondSession->id, 'is_active' => true]);

        $context = app(AcademicContextService::class);

        $this->assertTrue($firstSemester->is($context->activeSemesterForCourse($firstCourse)));
        $this->assertTrue($secondSemester->is($context->activeSemesterForCourse($secondCourse)));
        $this->assertFalse($context->semesterBelongsToCourse($firstSemester, $secondCourse));
    }

    private function makeUniversityCourse(string $label): array
    {
        $university = University::factory()->create(['name' => $label.' University']);
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $course = Course::factory()->create(['department_id' => $department->id]);

        return [$university, $course];
    }
}
