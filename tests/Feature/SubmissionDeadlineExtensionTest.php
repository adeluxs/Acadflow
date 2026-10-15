<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionExtension;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ensures an approved per-student deadline extension is reflected in the
 * student-facing assignment views and honoured when submitting.
 */
class SubmissionDeadlineExtensionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,\App\Models\Model> */
    private function context(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'is_active' => true, 'start_date' => now()->subWeek(), 'end_date' => now()->addMonths(3)]);
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

        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        // A task whose base deadline has already passed.
        $task = SubmissionTask::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'published',
            'is_visible_to_students' => true,
            'allow_late_submissions' => false,
            'open_at' => now()->subDays(10),
            'due_date' => now()->subDays(2),
            'close_at' => now()->subDay(),
            'late_deadline' => now()->subDay(),
            'allowed_file_types' => ['pdf'],
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'student', 'lecturer', 'task');
    }

    public function test_approved_extension_extends_the_effective_deadline(): void
    {
        $ctx = $this->context();

        $extensionUntil = now()->addDays(3);
        SubmissionExtension::create([
            'submission_task_id' => $ctx['task']->id,
            'student_id' => $ctx['student']->id,
            'original_deadline' => $ctx['task']->close_at,
            'extended_deadline' => $extensionUntil,
            'granted_by' => $ctx['lecturer']->id,
            'status' => 'approved',
        ]);

        $effective = $ctx['task']->getEffectiveDeadline($ctx['student']);
        $this->assertNotNull($effective);
        $this->assertSame($extensionUntil->timestamp, $effective->timestamp);

        // A student without an extension still sees the original deadline.
        $other = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $ctx['university']->id]);
        $this->assertSame($ctx['task']->close_at->timestamp, $ctx['task']->getEffectiveDeadline($other)->timestamp);
    }

    public function test_extension_is_shown_in_student_assignment_views(): void
    {
        $ctx = $this->context();
        SubmissionExtension::create([
            'submission_task_id' => $ctx['task']->id,
            'student_id' => $ctx['student']->id,
            'original_deadline' => $ctx['task']->close_at,
            'extended_deadline' => now()->addDays(3),
            'granted_by' => $ctx['lecturer']->id,
            'status' => 'approved',
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('courses.assignments', $ctx['course']))
            ->assertOk()
            ->assertSee('Extension granted');

        $this->actingAs($ctx['student'])
            ->get(route('submission-tasks.student.show', [$ctx['course'], $ctx['task']]))
            ->assertOk()
            ->assertSee('Deadline extended');
    }

    public function test_create_page_shows_the_extension_and_no_closed_message(): void
    {
        $ctx = $this->context();
        SubmissionExtension::create([
            'submission_task_id' => $ctx['task']->id,
            'student_id' => $ctx['student']->id,
            'granted_by' => $ctx['lecturer']->id,
            'original_deadline' => $ctx['task']->close_at,
            'extended_deadline' => now()->addDays(3),
            'status' => 'approved',
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('submissions.create', ['task_id' => $ctx['task']->id]))
            ->assertOk()
            ->assertSee('deadline extended')
            ->assertDontSee('Hard deadline passed')
            ->assertDontSee('Submissions are no longer accepted');
    }

    public function test_submission_is_accepted_within_the_extension_and_not_flagged_late(): void
    {
        Storage::fake('local');
        $ctx = $this->context();
        SubmissionExtension::create([
            'submission_task_id' => $ctx['task']->id,
            'student_id' => $ctx['student']->id,
            'original_deadline' => $ctx['task']->close_at,
            'extended_deadline' => now()->addDays(3),
            'granted_by' => $ctx['lecturer']->id,
            'status' => 'approved',
        ]);

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Extended submission',
            'status' => 'draft',
        ]);
        $path = 'submissions/'.$submission->uuid.'/answer.pdf';
        Storage::disk('local')->put($path, 'answer');
        SubmissionVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'file_name' => 'answer.pdf',
            'file_path' => $path,
            'file_size' => 6,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $ctx['student']->id,
            'is_current' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertRedirect(route('submissions.show', $submission));

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
        $this->assertFalse((bool) $submission->is_late);
    }

    public function test_submission_is_rejected_after_the_deadline_without_an_extension(): void
    {
        Storage::fake('local');
        $ctx = $this->context();

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Late submission',
            'status' => 'draft',
        ]);
        $path = 'submissions/'.$submission->uuid.'/answer.pdf';
        Storage::disk('local')->put($path, 'answer');
        SubmissionVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'file_name' => 'answer.pdf',
            'file_path' => $path,
            'file_size' => 6,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $ctx['student']->id,
            'is_current' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertSessionHas('error');

        $this->assertSame('draft', $submission->fresh()->status);
    }
}
