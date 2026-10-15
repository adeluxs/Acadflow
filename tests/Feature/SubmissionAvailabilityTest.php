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
 * Ensures a genuinely available assignment is never rejected on the student
 * submission-create page. Availability must follow the student's own
 * enrollments (course + semester), matching SubmissionTaskPolicy::view, and
 * must not depend on the student's own university field.
 */
class SubmissionAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,\App\Models\Model> */
    private function context(): array
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

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course');
    }

    private function taskFor(array $ctx, array $attributes = []): SubmissionTask
    {
        return SubmissionTask::factory()->create(array_merge([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'published',
            'is_visible_to_students' => true,
            'allowed_file_types' => ['pdf'],
        ], $attributes));
    }

    private function enroll(User $student, array $ctx): void
    {
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }

    public function test_student_without_a_university_can_open_an_enrolled_assignment(): void
    {
        // Regression: a student with no university on their profile (or from a
        // different institution) was wrongly told the assignment was unavailable
        // because availability applied the student's university as a filter.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'independent_professional',
            'university_id' => null,
            'department_id' => null,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx);

        $this->actingAs($student)
            ->get(route('submissions.create', ['task_id' => $task->id]))
            ->assertOk()
            ->assertViewIs('submissions.create')
            ->assertViewHas('task', fn ($selected) => $selected?->is($task));
    }

    public function test_institutional_student_can_open_their_course_assignment(): void
    {
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx);

        $this->actingAs($student)
            ->get(route('submissions.create', ['task_id' => $task->id]))
            ->assertOk()
            ->assertViewIs('submissions.create');
    }

    public function test_student_dashboard_renders_with_due_soon_assignments(): void
    {
        // Regression: the due-soon panel builds a task-detail link from the
        // task's course. Course uses `uuid` as its route key, so the eager load
        // must include uuid or route() reports a missing "course" parameter.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $this->taskFor($ctx, ['due_date' => now()->addDays(3), 'close_at' => now()->addDays(5)]);

        $this->actingAs($student)
            ->get(route('submissions.dashboard'))
            ->assertOk()
            ->assertViewIs('submissions.student-dashboard')
            ->assertSee('Due soon');
    }

    public function test_assignment_from_an_unenrolled_course_is_rejected(): void
    {
        $ctx = $this->context();
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $ctx['university']->id]);
        $this->enroll($student, $ctx);

        $otherDepartment = Department::factory()->create(['faculty_id' => $ctx['faculty']->id]);
        $otherCourse = Course::factory()->create(['department_id' => $otherDepartment->id]);
        $task = SubmissionTask::factory()->create([
            'course_id' => $otherCourse->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        $this->actingAs($student)
            ->get(route('submissions.create', ['task_id' => $task->id]))
            ->assertRedirect(route('submissions.create'))
            ->assertSessionHas('error');
    }

    public function test_disabled_siwes_assignment_is_not_openable_from_create(): void
    {
        $ctx = $this->context();
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $ctx['university']->id]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['type' => 'siwes']);

        $this->actingAs($student)
            ->get(route('submissions.create', ['task_id' => $task->id]))
            ->assertRedirect(route('submissions.create'))
            ->assertSessionHas('error');
    }

    public function test_enrolled_student_without_a_university_can_save_a_draft_submission(): void
    {
        // Regression: create() deliberately ignores the student's own university
        // (enrollment is the authorization), but store() used to abort(403) when
        // the course belonged to a university while the student's profile had
        // none. That inconsistency surfaced the global "Access Denied" page the
        // moment an independent student tried to save their assignment.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'independent_professional',
            'university_id' => null,
            'department_id' => null,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx);

        $response = $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Independent student draft',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertDatabaseHas('submissions', [
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'status' => 'draft',
        ]);
    }

    public function test_enrolled_student_is_not_denied_when_the_course_university_differs(): void
    {
        // Enrollment (not the profile university) is the authorization boundary,
        // so a legitimately enrolled student must never hit the 403 page when the
        // course's institution differs from their own profile.
        $ctx = $this->context();
        $otherUniversity = University::factory()->create();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $otherUniversity->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx);

        $response = $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Cross-institution enrollment draft',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertDatabaseHas('submissions', [
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_form_submit_intent_completes_the_submission(): void
    {
        // The create form's "Submit Assignment" button posts intent=submit so the
        // student finishes the submission in one step and the detail page shows
        // the accurate submitted status instead of "Draft".
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $response = $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'One step submission',
            'content' => '<p>My complete answer.</p>',
            'intent' => 'submit',
        ]);

        $submission = Submission::latest('id')->first();
        $this->assertNotNull($submission);
        $response->assertRedirect(route('submissions.show', $submission));
        $this->assertSame('submitted', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->submitted_at);
    }

    public function test_submitted_detail_page_shows_status_and_hides_upload_controls(): void
    {
        // Once a submission is complete the detail page must present the real
        // status and must not offer the draft-only upload/file controls.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Submitted work',
            'content' => '<p>Answer.</p>',
            'intent' => 'submit',
        ]);

        $submission = Submission::latest('id')->first();

        $this->actingAs($student)
            ->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertDontSee('Add or replace files')
            ->assertDontSee('Upload file(s)')
            ->assertDontSee('Submit for review');
    }

    public function test_plain_store_keeps_the_submission_as_a_draft(): void
    {
        // Non-regression: saving without intent must still leave a draft so the
        // existing two-step lifecycle keeps working.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Draft only',
            'content' => '<p>Draft.</p>',
        ]);

        $this->assertSame('draft', Submission::latest('id')->first()->status);
    }

    public function test_text_draft_detail_hides_the_file_upload_control(): void
    {
        // A text assignment never accepts files, so the detail page must not
        // render the "Add or replace files" / upload control for it.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Text draft',
            'content' => '<p>Draft answer.</p>',
        ]);

        $submission = Submission::latest('id')->first();

        $this->actingAs($student)
            ->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Add or replace files')
            ->assertDontSee('Upload file(s)')
            ->assertSee('Submit for review');
    }

    public function test_file_draft_detail_shows_the_file_upload_control(): void
    {
        // File-accepting formats keep the upload control on a draft.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'file']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'File draft',
        ]);

        $submission = Submission::latest('id')->first();

        $this->actingAs($student)
            ->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Add or replace files')
            ->assertSee('Upload file(s)');
    }

    public function test_text_draft_is_editable_and_update_is_sanitized(): void
    {
        // An unsubmitted draft must be editable: the detail page offers an edit
        // action, the edit form renders the rich editor, and the update stores
        // the written answer sanitized.
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Draft title',
            'content' => '<p>Old answer.</p>',
        ]);

        $submission = Submission::latest('id')->first();

        $this->actingAs($student)
            ->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Edit draft');

        $this->actingAs($student)
            ->get(route('submissions.edit', $submission))
            ->assertOk()
            ->assertSee('Written answer')
            ->assertSee('data-rich-editor', false);

        $this->actingAs($student)
            ->put(route('submissions.update', $submission), [
                'title' => 'Updated title',
                'description' => '<p>Updated answer.</p><script>alert(1)</script>',
            ])
            ->assertRedirect(route('submissions.show', $submission));

        $submission->refresh();
        $this->assertSame('Updated title', $submission->title);
        $this->assertStringContainsString('Updated answer', (string) $submission->description);
        $this->assertStringNotContainsString('<script', (string) $submission->description);
    }

    public function test_submitted_submission_is_not_editable(): void
    {
        $ctx = $this->context();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
            'department_id' => $ctx['department']->id,
        ]);
        $this->enroll($student, $ctx);
        $task = $this->taskFor($ctx, ['submission_format' => 'text']);

        $this->actingAs($student)->post(route('submissions.store'), [
            'submission_task_id' => $task->id,
            'course_id' => $ctx['course']->id,
            'type' => 'assignment',
            'title' => 'Final work',
            'content' => '<p>Final.</p>',
            'intent' => 'submit',
        ]);

        $submission = Submission::latest('id')->first();

        $this->actingAs($student)
            ->get(route('submissions.edit', $submission))
            ->assertForbidden();
    }
}
