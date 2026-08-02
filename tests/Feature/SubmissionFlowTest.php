<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\SubmissionTask;
use App\Models\Submission;
use App\Models\Semester;
use App\Models\LecturerCourseAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_submission(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => 1,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('submissions.create', ['task_id' => $task->id]));

        $response->assertStatus(200);
    }

    public function test_student_can_submit_assignment(): void
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => 1,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $submission = Submission::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => 1,
            'submission_task_id' => $task->id,
            'type' => 'assignment',
            'title' => 'Test Submission',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($student)->post(route('submissions.submit', $submission));

        $response->assertRedirect(route('submissions.show', $submission));
        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'status' => 'submitted',
        ]);
    }

    public function test_lecturer_can_grade_submission(): void
    {
        $lecturer = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id]);
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => 1,
            'is_coordinator' => false,
        ]);
        $submission = Submission::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => 1,
            'submission_task_id' => $task->id,
            'type' => 'assignment',
            'title' => 'Test Submission',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($lecturer)->post(route('submissions.grade', $submission), [
            'score' => 85,
            'feedback' => 'Good work',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('submission_grades', [
            'submission_id' => $submission->id,
            'score' => 85,
        ]);
    }
}
