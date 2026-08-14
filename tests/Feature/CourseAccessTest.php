<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
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
}
