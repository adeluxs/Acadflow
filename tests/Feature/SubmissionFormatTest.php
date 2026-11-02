<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers format-aware submission: text, file and both. The create page renders
 * the right sections and the backend requires the right content.
 */
class SubmissionFormatTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,\App\Models\Model> */
    private function context(string $format = 'file'): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_active' => true]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'is_active' => true,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addMonths(3),
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $task = SubmissionTask::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'published',
            'is_visible_to_students' => true,
            'submission_format' => $format,
            'allowed_file_types' => ['pdf'],
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'student', 'task');
    }

    public function test_text_format_create_page_shows_the_editor_and_hides_the_uploader(): void
    {
        $ctx = $this->context('text');

        $this->actingAs($ctx['student'])
            ->get(route('submissions.create', ['task_id' => $ctx['task']->id]))
            ->assertOk()
            ->assertSee('Your Written Answer')
            ->assertSee('data-rich-editor', false)
            ->assertDontSee('Upload Your Files');
    }

    public function test_file_format_create_page_shows_the_uploader_and_hides_the_editor(): void
    {
        $ctx = $this->context('file');

        $this->actingAs($ctx['student'])
            ->get(route('submissions.create', ['task_id' => $ctx['task']->id]))
            ->assertOk()
            ->assertSee('Upload Your Files')
            ->assertDontSee('Your Written Answer');
    }

    public function test_both_format_create_page_shows_both_sections(): void
    {
        $ctx = $this->context('both');

        $this->actingAs($ctx['student'])
            ->get(route('submissions.create', ['task_id' => $ctx['task']->id]))
            ->assertOk()
            ->assertSee('Your Written Answer')
            ->assertSee('Upload Your Files');
    }

    public function test_text_submission_requires_written_content(): void
    {
        $ctx = $this->context('text');

        $this->actingAs($ctx['student'])
            ->post(route('submissions.store'), [
                'course_id' => $ctx['course']->id,
                'submission_task_id' => $ctx['task']->id,
                'type' => 'assignment',
                'title' => 'Text answer',
            ])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_text_submission_is_stored_and_can_be_submitted(): void
    {
        $ctx = $this->context('text');

        $this->actingAs($ctx['student'])
            ->post(route('submissions.store'), [
                'course_id' => $ctx['course']->id,
                'submission_task_id' => $ctx['task']->id,
                'type' => 'assignment',
                'title' => 'Text answer',
                'content' => '<p>My written answer with <strong>emphasis</strong>.</p>',
            ])
            ->assertRedirect();

        $submission = Submission::latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertStringContainsString('My written answer', (string) $submission->description);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertRedirect(route('submissions.show', $submission));

        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_assignment_form_exposes_format_field_grouping(): void
    {
        $ctx = $this->context('file');

        // Create form is lecturer-facing; assert the partial exposes the format
        // grouping and the text guidance used to hide file fields for text.
        $this->actingAs($this->lecturer($ctx))
            ->get(route('submission-tasks.create', $ctx['course']))
            ->assertOk()
            ->assertSee('data-format-fields="file"', false)
            ->assertSee('Text submission');
    }

    private function lecturer(array $ctx): User
    {
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        \App\Models\LecturerCourseAssignment::create([
            'course_id' => $ctx['course']->id,
            'user_id' => $lecturer->id,
            'semester_id' => $ctx['semester']->id,
            'is_coordinator' => false,
        ]);

        return $lecturer;
    }
}
