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
use App\Models\SubmissionGradeHistory;
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
 * Covers assignment creation branching by type (assignment, group, project,
 * siwes, seminar) and the grading follow-ups: late penalty, grade history and
 * rubric criterion feedback.
 */
class AssignmentTypeCreationTest extends TestCase
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

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'lecturer', 'student');
    }

    /** @return array<string,mixed> */
    private function basePayload(array $ctx, string $type): array
    {
        return [
            'title' => ucfirst($type).' task',
            'type' => $type,
            'open_at' => now()->format('Y-m-d\TH:i'),
            'due_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'late_deadline' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'max_file_size_mb' => 10,
            'max_file_count' => 5,
            'min_file_count' => 1,
            'max_score' => 100,
            'submission_format' => 'file',
            'semester_id' => $ctx['semester']->id,
            'is_visible_to_students' => true,
            'status' => 'draft',
            'allowed_file_types' => 'pdf,docx',
        ];
    }

    public function test_normal_assignment_needs_no_type_specific_fields(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->basePayload($ctx, 'assignment'))
            ->assertRedirect();

        $this->assertDatabaseHas('submission_tasks', ['course_id' => $ctx['course']->id, 'type' => 'assignment']);
    }

    public function test_project_assignment_requires_project_fields(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->basePayload($ctx, 'project'))
            ->assertSessionHasErrors(['type_metadata.project_title', 'type_metadata.supervisor_name', 'type_metadata.proposal_deadline', 'type_metadata.milestones']);

        $this->assertDatabaseCount('submission_tasks', 0);
    }

    public function test_project_assignment_is_created_with_project_metadata(): void
    {
        $ctx = $this->context();

        $payload = array_merge($this->basePayload($ctx, 'project'), [
            'type_metadata' => [
                'project_title' => 'Smart attendance system',
                'supervisor_name' => 'Dr Ada',
                'proposal_deadline' => now()->addDays(3)->format('Y-m-d'),
                'defense_required' => '1',
                'milestones' => "Proposal\nChapter 1\nPrototype",
                'injected_key' => 'should be dropped',
            ],
        ]);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $payload)
            ->assertRedirect();

        $task = SubmissionTask::latest('id')->first();
        $this->assertSame('project', $task->type);
        $this->assertSame('Dr Ada', $task->type_metadata['supervisor_name']);
        $this->assertTrue($task->type_metadata['defense_required']);
        // Unknown keys are never persisted.
        $this->assertArrayNotHasKey('injected_key', $task->type_metadata);
    }

    public function test_siwes_assignment_captures_placement_details(): void
    {
        $ctx = $this->context();
        // SIWES is disabled by default, so enable it for this type-specific test.
        \App\Services\SettingService::set('siwes_assignment_enabled', true, 'boolean');

        $payload = array_merge($this->basePayload($ctx, 'siwes'), [
            'type_metadata' => [
                'placement_organisation' => 'Acme Ltd',
                'industry_supervisor' => 'Engr Bello',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addMonths(3)->format('Y-m-d'),
                'duration_weeks' => 12,
                'logbook_expectation' => 'weekly',
                'evaluation_method' => 'both',
            ],
        ]);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $payload)
            ->assertRedirect();

        $task = SubmissionTask::latest('id')->first();
        $this->assertSame('siwes', $task->type);
        $this->assertSame('Acme Ltd', $task->type_metadata['placement_organisation']);
        $this->assertSame(12, $task->type_metadata['duration_weeks']);
        $this->assertSame('both', $task->type_metadata['evaluation_method']);
    }

    public function test_seminar_assignment_captures_topic_and_panel(): void
    {
        $ctx = $this->context();

        $payload = array_merge($this->basePayload($ctx, 'seminar'), [
            'type_metadata' => [
                'topic' => 'AI in education',
                'abstract' => 'A study of AI adoption.',
                'presentation_date' => now()->addDays(20)->format('Y-m-d'),
                'topic_approval_required' => '1',
                'panel_members' => "Prof X\nDr Y",
            ],
        ]);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $payload)
            ->assertRedirect();

        $task = SubmissionTask::latest('id')->first();
        $this->assertSame('seminar', $task->type);
        $this->assertSame('AI in education', $task->type_metadata['topic']);
        $this->assertTrue($task->type_metadata['topic_approval_required']);
    }

    public function test_group_assignment_requires_group_formation(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->basePayload($ctx, 'group'))
            ->assertSessionHasErrors(['type_metadata.group_formation']);
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->basePayload($ctx, 'internship'))
            ->assertSessionHasErrors(['type']);

        $this->assertDatabaseCount('submission_tasks', 0);
    }

    private function gradedContext(array $taskAttributes = [], bool $late = false): array
    {
        $ctx = $this->context();
        $task = SubmissionTask::factory()->create(array_merge([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'published',
            'is_visible_to_students' => true,
            'allowed_file_types' => ['pdf'],
        ], $taskAttributes));

        Storage::fake('local');
        $submission = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'submission_task_id' => $task->id,
            'type' => 'assignment',
            'title' => 'Late work',
            'status' => 'submitted',
            'is_late' => $late,
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

        $ctx['task'] = $task;
        $ctx['submission'] = $submission;

        return $ctx;
    }

    public function test_late_penalty_is_applied_and_recorded_at_grade_time(): void
    {
        $ctx = $this->gradedContext(['late_submission_penalty_percent' => 10], true);

        \App\Models\LateSubmission::create([
            'submission_id' => $ctx['submission']->id,
            'submission_task_id' => $ctx['task']->id,
            'submitted_at' => now(),
            'deadline_at' => now()->subDay(),
            'minutes_late' => 1440,
        ]);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $ctx['submission']), ['score' => 80, 'feedback' => 'Late but solid'])
            ->assertRedirect();

        $grade = SubmissionGrade::where('submission_id', $ctx['submission']->id)->first();
        $this->assertEquals(72, (float) $grade->score);

        $this->assertDatabaseHas('late_submissions', [
            'submission_id' => $ctx['submission']->id,
            'score_before_penalty' => 80,
            'score_after_penalty' => 72,
        ]);
    }

    public function test_grade_history_is_recorded_on_every_grade(): void
    {
        $ctx = $this->gradedContext();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $ctx['submission']), ['score' => 65, 'feedback' => 'First pass'])
            ->assertRedirect();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $ctx['submission']), ['score' => 70, 'feedback' => 'Regrade'])
            ->assertRedirect();

        $this->assertSame(2, SubmissionGradeHistory::where('submission_id', $ctx['submission']->id)->count());
        $this->assertDatabaseHas('submission_grade_histories', [
            'submission_id' => $ctx['submission']->id,
            'score' => 70,
        ]);
    }

    public function test_rubric_criterion_feedback_is_stored(): void
    {
        $ctx = $this->context();
        $rubric = SubmissionRubric::create([
            'course_id' => $ctx['course']->id,
            'name' => 'Report rubric',
            'criteria' => [
                ['name' => 'Argument', 'points' => 50],
                ['name' => 'Clarity', 'points' => 50],
            ],
            'total_points' => 100,
            'is_active' => true,
        ]);
        $ctx = $this->gradedContext(['rubric_id' => $rubric->id, 'max_score' => 100]);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submissions.grade', $ctx['submission']), [
                'criteria' => ['Argument' => 40, 'Clarity' => 35],
                'criteria_feedback' => ['Argument' => 'Tighten the thesis', 'Clarity' => 'Good flow'],
                'feedback' => 'Solid work',
            ])
            ->assertRedirect();

        $grade = SubmissionGrade::where('submission_id', $ctx['submission']->id)->first();
        $this->assertSame('Tighten the thesis', $grade->criteria_feedback['Argument']);
        $this->assertSame('Good flow', $grade->criteria_feedback['Clarity']);
    }
}
