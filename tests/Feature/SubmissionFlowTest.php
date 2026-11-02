<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{student:User,course:Course,task:SubmissionTask,semester:Semester} */
    private function studentCourseContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'is_active' => true]);
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $task = SubmissionTask::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return compact('student', 'course', 'task', 'semester');
    }

    public function test_student_can_create_submission(): void
    {
        $ctx = $this->studentCourseContext();

        $this->actingAs($ctx['student'])
            ->get(route('submissions.create', ['task_id' => $ctx['task']->id]))
            ->assertOk();
    }

    public function test_student_can_submit_assignment(): void
    {
        Storage::fake('local');
        $ctx = $this->studentCourseContext();

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Test Submission',
            'status' => 'draft',
        ]);
        // The task only accepts pdf/doc/docx, so the fixture must use an
        // allowed extension for a successful submission.
        $path = 'submissions/'.$submission->uuid.'/answer.pdf';
        Storage::disk('local')->put($path, 'Test answer');
        SubmissionVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'file_name' => 'answer.pdf',
            'file_path' => $path,
            'file_size' => 11,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $ctx['student']->id,
            'is_current' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertRedirect(route('submissions.show', $submission));

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'status' => 'submitted',
        ]);
    }

    public function test_submit_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $ctx = $this->studentCourseContext();

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Wrong type',
            'status' => 'draft',
        ]);
        $path = 'submissions/'.$submission->uuid.'/answer.txt';
        Storage::disk('local')->put($path, 'answer');
        SubmissionVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'file_name' => 'answer.txt',
            'file_path' => $path,
            'file_size' => 6,
            'mime_type' => 'text/plain',
            'uploaded_by' => $ctx['student']->id,
            'is_current' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'status' => 'draft',
        ]);
    }

    public function test_lecturer_can_grade_submission(): void
    {
        $ctx = $this->studentCourseContext();
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['student']->university_id,
            'department_id' => $ctx['course']->department_id,
        ]);
        LecturerCourseAssignment::create([
            'course_id' => $ctx['course']->id,
            'user_id' => $lecturer->id,
            'semester_id' => $ctx['semester']->id,
            'is_coordinator' => false,
        ]);
        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Test Submission',
            'status' => 'submitted',
        ]);

        $this->actingAs($lecturer)
            ->post(route('submissions.grade', $submission), [
                'score' => 85,
                'feedback' => 'Good work',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('submission_grades', [
            'submission_id' => $submission->id,
            'score' => 85,
        ]);
    }
}
