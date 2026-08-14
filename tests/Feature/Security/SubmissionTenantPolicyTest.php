<?php

namespace Tests\Feature\Security;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\University;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTenantPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_university_admin_cannot_view_another_university_submission(): void
    {
        [$firstUniversity, $firstCourse, $firstSemester] = $this->makeAcademicContext();
        [$secondUniversity, $secondCourse, $secondSemester] = $this->makeAcademicContext();
        $admin = User::factory()->create(['role' => 'university_admin', 'university_id' => $firstUniversity->id]);
        $owner = User::factory()->create(['university_id' => $secondUniversity->id]);
        $submission = Submission::factory()->create([
            'user_id' => $owner->id,
            'course_id' => $secondCourse->id,
            'semester_id' => $secondSemester->id,
        ]);

        $this->assertFalse(app(SubmissionPolicy::class)->view($admin, $submission));

        $localOwner = User::factory()->create(['university_id' => $firstUniversity->id]);
        $localSubmission = Submission::factory()->create([
            'user_id' => $localOwner->id,
            'course_id' => $firstCourse->id,
            'semester_id' => $firstSemester->id,
        ]);
        $this->assertTrue(app(SubmissionPolicy::class)->view($admin, $localSubmission));
    }

    private function makeAcademicContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $session = AcademicSession::factory()->create(['university_id' => $university->id]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id]);

        return [$university, $course, $semester];
    }
}
