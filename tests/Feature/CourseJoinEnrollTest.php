<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseJoinEnrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_institutional_join_link_works_for_same_institution_same_department_student(): void
    {
        $ctx = $this->makeContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertOk()
            ->assertViewIs('courses.join')
            ->assertViewHas('alreadyEnrolled', false);
    }

    public function test_join_link_for_already_enrolled_student_without_institution_is_not_denied(): void
    {
        $ctx = $this->makeContext();

        // Regression: a student with no institution on their profile but an
        // existing enrollment must not hit the global Access Denied page.
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => null,
            'department_id' => null,
        ]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->assertTrue($student->fresh()->canAccessCourse($ctx['course']));

        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertOk()
            ->assertViewIs('courses.join')
            ->assertViewHas('alreadyEnrolled', true);
    }

    public function test_enroll_is_idempotent_for_an_already_enrolled_student(): void
    {
        $ctx = $this->makeContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => null,
            'department_id' => null,
        ]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->post(route('courses.enroll', $ctx['course']))
            ->assertRedirect(route('courses.show', $ctx['course']))
            ->assertSessionHas('info');
    }

    public function test_student_can_enroll_in_same_institution_and_department_course(): void
    {
        $ctx = $this->makeContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->post(route('courses.enroll', $ctx['course']))
            ->assertRedirect(route('courses.show', $ctx['course']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_cross_institution_student_is_redirected_with_a_clear_message_not_a_denied_page(): void
    {
        $ctx = $this->makeContext();

        $otherUniversity = University::factory()->create();
        $otherFaculty = Faculty::factory()->create(['university_id' => $otherUniversity->id]);
        $otherDepartment = Department::factory()->create(['faculty_id' => $otherFaculty->id]);

        $outsider = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($outsider)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertRedirect(route('courses.index'))
            ->assertSessionHas('error');

        $this->actingAs($outsider)
            ->post(route('courses.enroll', $ctx['course']))
            ->assertRedirect(route('courses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $outsider->id,
            'course_id' => $ctx['course']->id,
        ]);
    }

    public function test_cross_department_student_is_denied_when_restriction_enabled_and_allowed_when_disabled(): void
    {
        $ctx = $this->makeContext();

        $otherDepartment = Department::factory()->create(['faculty_id' => $ctx['faculty']->id,'adoption_status'=>'institution_managed']);
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $otherDepartment->id,
        ]);

        // Default: restrict_course_membership_to_department = true
        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertRedirect(route('courses.index'))
            ->assertSessionHas('error');

        // Administrator disables the department restriction.
        SettingService::set('restrict_course_membership_to_department', false, 'boolean', $ctx['university']->id, null);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertOk()
            ->assertViewIs('courses.join');
    }

    public function test_non_student_join_link_still_redirects_instead_of_showing_denied(): void
    {
        $ctx = $this->makeContext();
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->actingAs($lecturer)
            ->get(route('courses.join.link', $ctx['course']->uuid))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    /**
     * @return array<string,\App\Models\Model>
     */
    private function approveProfile(User $user): void
    {
        if (!$user->university_id) return;
        \App\Models\UserInstitutionAffiliation::firstOrCreate(
            ['user_id'=>$user->id,'university_id'=>$user->university_id,'department_id'=>$user->department_id,'affiliation_type'=>$user->role],
            ['faculty_id'=>$user->department?->faculty_id,'status'=>'verified','verified_at'=>now(),'source'=>'test_review']
        );
    }

    private function makeContext(): array
    {
        $university = University::factory()->create(['adoption_status'=>'active']);
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id,'adoption_status'=>'institution_managed']);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_current' => true, 'is_active' => true]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonths(4),
        ]);
        $course = Course::factory()->create(['department_id' => $department->id, 'access_mode' => 'institutional']);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course');
    }
}
