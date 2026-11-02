<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Services\IndependentAcademicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_enrolled_course(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('courses.show', $course));

        $response->assertStatus(200);
        $response->assertSee($course->name);
    }

    public function test_student_cannot_view_unenrolled_course(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);

        $response = $this->actingAs($student)->get(route('courses.show', $course));

        $response->assertStatus(403);
    }

    public function test_lecturer_can_view_own_course(): void
    {
        $lecturer = User::factory()->create(['role' => 'lecturer']);
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create();
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $semester->id,
            'is_coordinator' => false,
        ]);

        $response = $this->actingAs($lecturer)->get(route('courses.show', $course));

        $response->assertStatus(200);
    }

    public function test_lecturer_cannot_view_other_lecturer_course(): void
    {
        $lecturer = User::factory()->create(['role' => 'lecturer']);
        $otherLecturer = User::factory()->create(['role' => 'lecturer']);
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create();
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $otherLecturer->id,
            'semester_id' => $semester->id,
            'is_coordinator' => false,
        ]);

        $response = $this->actingAs($lecturer)->get(route('courses.show', $course));

        $response->assertStatus(403);
    }

    public function test_student_can_open_join_link_for_independent_course_without_prior_enrollment(): void
    {
        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);

        $course = Course::factory()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'name' => 'Independent Course Join Test',
            'semester' => 'Independent',
        ]);

        $response = $this->actingAs($student)->get(route('courses.join.link', $course->uuid));

        $response->assertStatus(200);
        $response->assertViewIs('courses.join');
        $response->assertViewHas('course', fn (Course $viewCourse) => $viewCourse->is($course));
        $response->assertViewHas('alreadyEnrolled', false);
    }

    public function test_student_can_enroll_in_independent_course_without_view_access_first(): void
    {
        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);

        $course = Course::factory()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'semester' => 'Independent',
        ]);

        $this->assertFalse($student->canAccessCourse($course));

        $response = $this->actingAs($student)->post(route('courses.enroll', $course));

        $response->assertRedirect(route('courses.show', $course));
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $context['semester']->id,
            'status' => 'enrolled',
        ]);
        $this->assertTrue($student->fresh()->canAccessCourse($course));
    }

    public function test_non_student_join_link_redirects_instead_of_access_denied(): void
    {
        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $course = Course::factory()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'semester' => 'Independent',
        ]);

        $response = $this->actingAs($lecturer)->get(route('courses.join.link', $course->uuid));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Only student accounts can join a course.');
    }
}
