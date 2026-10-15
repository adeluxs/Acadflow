<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the role-aware authenticated user profile page (profile.show).
 *
 * These tests assert that the page presents identity, institution membership,
 * academic session, role permissions, enrollment status and access restrictions
 * from the existing models/relationships, and that access is authenticated.
 */
class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_student_profile_presents_identity_institution_and_membership(): void
    {
        $ctx = $this->makeAcademicContext();

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
            'faculty_id' => $ctx['faculty']->id,
        ]);

        $response = $this->actingAs($student)->get(route('profile.show'));

        $response->assertOk()
            ->assertViewIs('profile.show')
            ->assertViewHas('university', fn ($university) => $university?->is($ctx['university']))
            ->assertViewHas('department', fn ($department) => $department?->is($ctx['department']))
            ->assertViewHas('membershipStatus', fn ($status) => $status['is_member'] === true)
            ->assertSee($student->full_name)
            ->assertSee($ctx['university']->name)
            ->assertSee($ctx['department']->name)
            ->assertSee('Institution & membership', false)
            ->assertSee('Role & permissions', false);
    }

    public function test_active_session_and_semester_are_resolved_for_the_institution(): void
    {
        $ctx = $this->makeAcademicContext();

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('academicSession', fn ($session) => $session?->is($ctx['session']))
            ->assertViewHas('activeSemester', fn ($semester) => $semester?->is($ctx['semester']))
            ->assertSee($ctx['session']->name)
            ->assertSee($ctx['semester']->name);
    }

    public function test_profile_splits_active_and_inactive_enrollments_and_lists_pending_invitations(): void
    {
        $ctx = $this->makeAcademicContext();

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $activeCourse = Course::factory()->create(['department_id' => $ctx['department']->id, 'access_mode' => 'institutional']);
        $droppedCourse = Course::factory()->create(['department_id' => $ctx['department']->id, 'access_mode' => 'institutional']);
        $invitedCourse = Course::factory()->create(['department_id' => $ctx['department']->id, 'access_mode' => 'institutional']);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $activeCourse->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $droppedCourse->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'dropped',
            'enrolled_at' => now()->subMonth(),
        ]);

        $inviter = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        CourseInvitation::query()->create([
            'course_id' => $invitedCourse->id,
            'semester_id' => $ctx['semester']->id,
            'invited_by' => $inviter->id,
            'email' => $student->email,
            'token_hash' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('activeEnrollments', fn ($enrollments) => $enrollments->count() === 1)
            ->assertViewHas('inactiveEnrollments', fn ($enrollments) => $enrollments->count() === 1)
            ->assertViewHas('invitations', fn ($invitations) => $invitations->count() === 1)
            ->assertSee($activeCourse->name)
            ->assertSee($droppedCourse->name)
            ->assertSee($invitedCourse->name)
            ->assertSee('Pending course invitations');
    }

    public function test_independent_student_is_told_they_have_no_institution_and_has_no_restrictions(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'independent_professional',
            'university_id' => null,
            'department_id' => null,
        ]);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('membershipStatus', fn ($status) => $status['is_member'] === false)
            ->assertSee('Independent')
            ->assertSee('No institution on your profile');
    }

    public function test_member_with_institution_and_department_has_no_access_restrictions(): void
    {
        $ctx = $this->makeAcademicContext();

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('restrictions', fn ($restrictions) => $restrictions === [])
            ->assertSee('Your account has no access restrictions.');
    }

    public function test_lecturer_profile_lists_teaching_assignments_and_role_permissions(): void
    {
        $ctx = $this->makeAcademicContext();

        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $course = Course::factory()->create(['department_id' => $ctx['department']->id, 'access_mode' => 'institutional']);

        LecturerCourseAssignment::query()->create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $ctx['semester']->id,
            'is_coordinator' => true,
        ]);

        $this->actingAs($lecturer)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('teachingCourses', fn ($assignments) => $assignments->count() === 1)
            ->assertSee('Teaching & management', false)
            ->assertSee($course->name)
            ->assertSee('Coordinator')
            ->assertSee('Grade Submission');
    }

    public function test_request_only_exposes_the_authenticated_users_own_data(): void
    {
        $ctx = $this->makeAcademicContext();

        $me = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $other = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);

        $this->actingAs($me)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewHas('user', fn ($user) => $user->is($me))
            ->assertSee($me->email)
            ->assertDontSee($other->email);
    }

    /**
     * @return array<string,\App\Models\Model>
     */
    private function makeAcademicContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create([
            'university_id' => $university->id,
            'is_current' => true,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'is_active' => true,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(3),
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester');
    }
}
