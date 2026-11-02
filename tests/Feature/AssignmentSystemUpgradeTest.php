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
use App\Models\SubmissionGrade;
use App\Models\SubmissionRubric;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the assignment-system upgrade: grading correctness (max score,
 * graded_at, rubric totals), reviewer authorization, submission integrity
 * (late flagging) and grade-export scoping.
 */
class AssignmentSystemUpgradeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,\App\Models\Model> */
    private function context(array $taskAttributes = []): array
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
        $task = SubmissionTask::factory()->create(array_merge([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'published',
            'is_visible_to_students' => true,
        ], $taskAttributes));

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
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $semester->id,
            'is_coordinator' => false,
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'student', 'course', 'task', 'lecturer');
    }

    private function submissionWithFile(array $ctx, array $attributes = []): Submission
    {
        Storage::fake('local');
        $submission = Submission::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $ctx['task']->id,
            'type' => 'assignment',
            'title' => 'Test Submission',
            'status' => 'submitted',
        ], $attributes));

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

        return $submission;
    }

    public function test_grade_respects_task_max_score_and_sets_graded_at(): void
    {
        $ctx = $this->context(['max_score' => 200]);
        $submission = $this->submissionWithFile($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $submission), ['score' => 150, 'feedback' => 'Strong'])
            ->assertRedirect();

        $grade = SubmissionGrade::where('submission_id', $submission->id)->first();
        $this->assertNotNull($grade);
        $this->assertEquals(150, (float) $grade->score);
        $this->assertEquals(200, (float) $grade->max_score);
        $this->assertNotNull($submission->fresh()->graded_at);
    }

    public function test_rubric_grading_derives_total_and_stores_criteria(): void
    {
        $ctx = $this->context();
        $rubric = SubmissionRubric::create([
            'course_id' => $ctx['course']->id,
            'name' => 'Essay rubric',
            'criteria' => [
                ['name' => 'Argument', 'points' => 50],
                ['name' => 'Clarity', 'points' => 50],
            ],
            'total_points' => 100,
            'is_active' => true,
        ]);
        $ctx['task']->update(['rubric_id' => $rubric->id, 'max_score' => 100]);
        $submission = $this->submissionWithFile($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $submission), [
                'criteria' => ['Argument' => 40, 'Clarity' => 35],
                'feedback' => 'Good structure',
            ])
            ->assertRedirect();

        $grade = SubmissionGrade::where('submission_id', $submission->id)->first();
        $this->assertEquals(75, (float) $grade->score);
        $this->assertEquals(40, (float) $grade->criteria_scores['Argument']);
        $this->assertEquals(35, (float) $grade->criteria_scores['Clarity']);
        $this->assertEquals($rubric->id, $grade->rubric_id);
    }

    public function test_lecturer_comment_is_stored_on_the_content_column(): void
    {
        $ctx = $this->context();
        $submission = $this->submissionWithFile($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.comment', $submission), ['comment' => 'Please expand the conclusion.'])
            ->assertRedirect();

        $this->assertDatabaseHas('submission_comments', [
            'submission_id' => $submission->id,
            'content' => 'Please expand the conclusion.',
            'type' => 'general',
        ]);
    }

    public function test_request_correction_sets_the_correction_status(): void
    {
        $ctx = $this->context();
        $submission = $this->submissionWithFile($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.request-correction', $submission))
            ->assertRedirect();

        $this->assertSame('correction_requested', $submission->fresh()->status);
    }

    public function test_lecturer_index_shows_own_submissions_and_excludes_foreign_courses(): void
    {
        $ctx = $this->context();
        $mine = $this->submissionWithFile($ctx);

        // A submission in a completely different institution must never appear.
        $otherUniversity = University::factory()->create();
        $otherFaculty = Faculty::factory()->create(['university_id' => $otherUniversity->id]);
        $otherDepartment = Department::factory()->create(['faculty_id' => $otherFaculty->id]);
        $otherCourse = Course::factory()->create(['department_id' => $otherDepartment->id]);
        $outsider = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
        ]);
        Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $outsider->id,
            'course_id' => $otherCourse->id,
            'semester_id' => $ctx['semester']->id,
            'type' => 'assignment',
            'title' => 'Foreign submission',
            'status' => 'submitted',
        ]);

        $this->actingAs($ctx['lecturer'])
            ->get(route('submissions.lecturer-index'))
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee('Foreign submission');
    }

    public function test_bulk_approve_updates_selected_submissions(): void
    {
        $ctx = $this->context();
        $first = $this->submissionWithFile($ctx);
        $second = $this->submissionWithFile($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.bulk'), [
                'action' => 'approve',
                'submission_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('approved', $second->fresh()->status);
    }

    public function test_late_submission_is_flagged_and_recorded(): void
    {
        $ctx = $this->context([
            'close_at' => now()->subDay(),
            'late_deadline' => now()->addDays(3),
            'allow_late_submissions' => true,
            'late_submission_penalty_percent' => 10,
        ]);
        $submission = $this->submissionWithFile($ctx, ['status' => 'draft']);

        $this->actingAs($ctx['student'])
            ->post(route('submissions.submit', $submission))
            ->assertRedirect(route('submissions.show', $submission));

        $submission->refresh();
        $this->assertTrue((bool) $submission->is_late);
        $this->assertSame('submitted', $submission->status);

        $this->assertDatabaseHas('late_submissions', [
            'submission_id' => $submission->id,
            'submission_task_id' => $ctx['task']->id,
        ]);
    }
}
