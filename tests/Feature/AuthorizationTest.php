<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\LecturerCourseAssignment;
use App\Models\Enrollment;
use App\Models\Department;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_own_submission(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        $submission = Submission::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => Semester::factory()->create()->id,
            'submission_task_id' => $task->id,
        ]);

        $response = $this->actingAs($student)->get(route('submissions.show', $submission));

        $response->assertStatus(200);
    }

    public function test_student_cannot_view_other_student_submission(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        $submission = Submission::factory()->create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'semester_id' => Semester::factory()->create()->id,
            'submission_task_id' => $task->id,
        ]);

        $response = $this->actingAs($student)->get(route('submissions.show', $submission));

        $response->assertStatus(403);
    }

    public function test_lecturer_can_view_course_submissions(): void
    {
        $lecturer = User::factory()->create(['role' => 'super_admin']);
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        $student = User::factory()->create();
        $semester = Semester::factory()->create();
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $semester->id,
            'is_coordinator' => false,
        ]);
        $submission = Submission::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'submission_task_id' => $task->id,
        ]);

        $response = $this->actingAs($lecturer)->get(route('submissions.show', $submission));

        $response->assertStatus(200);
    }

    public function test_submission_policy_submit_method_exists(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        $submission = Submission::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => Semester::factory()->create()->id,
            'submission_task_id' => $task->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($student->can('submit', $submission));
    }

    public function test_faculty_policy_is_registered(): void
    {
        $provider = new \App\Providers\AuthServiceProvider(app());
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('policies');
        $property->setAccessible(true);
        $policies = $property->getValue($provider);
        
        $this->assertArrayHasKey(\App\Models\Faculty::class, $policies);
        $this->assertSame(\App\Policies\FacultyPolicy::class, $policies[\App\Models\Faculty::class]);
    }
}
