<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Jobs\GenerateLecturerContentDraft;
use App\Models\AcademicSession;
use App\Models\AiContentDraft;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use App\Services\Ai\LecturerContentAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LecturerContentDraftAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_endpoint_queues_jobs_and_creates_pending_drafts(): void
    {
        $ctx = $this->makeCourseContext();
        Queue::fake();

        $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.ai.batch', $ctx['course']), [
                'items' => [
                    ['action' => 'lesson_plan', 'instruction' => 'Week 4 lesson plan on normalization'],
                    ['action' => 'quiz', 'instruction' => 'Quiz on functional dependencies'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, AiContentDraft::query()->count());
        $this->assertSame(2, AiContentDraft::query()->where('status', AiContentDraft::STATUS_PENDING)->count());
        Queue::assertPushed(GenerateLecturerContentDraft::class, 2);
    }

    public function test_job_stores_a_ready_draft_without_creating_course_content(): void
    {
        $ctx = $this->makeCourseContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'lecturer_assistant',
                success: true,
                data: ['answer' => "Objective: understand 1NF.\n\nActivity: normalize a sample table."],
                provider: 'openrouter',
                model: 'google/gemini-2.5-flash',
            ));
        });

        $draft = AiContentDraft::query()->create([
            'user_id' => $ctx['lecturer']->id,
            'university_id' => $ctx['university']->id,
            'course_id' => $ctx['course']->id,
            'feature' => 'lecturer_assistant',
            'action' => 'lesson_plan',
            'instruction' => 'Week 4 lesson plan on normalization',
            'status' => AiContentDraft::STATUS_PENDING,
        ]);

        (new GenerateLecturerContentDraft($draft->id))->handle(app(LecturerContentAssistantService::class));

        $draft->refresh();
        $this->assertSame(AiContentDraft::STATUS_READY, $draft->status);
        $this->assertStringContainsString('Objective', (string) $draft->content);
        $this->assertNotNull($draft->generated_at);

        // Automation must not create course content on its own.
        $this->assertSame(0, CourseMaterial::query()->count());
    }

    public function test_applying_a_ready_draft_creates_an_ai_assisted_material_and_audits(): void
    {
        $ctx = $this->makeCourseContext();
        $draft = $this->readyDraft($ctx);

        $response = $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.ai.drafts.apply', [$ctx['course'], $draft]));

        $response->assertRedirect();

        $material = CourseMaterial::query()->first();
        $this->assertNotNull($material);
        $this->assertSame('ai_assisted', $material->source);
        $this->assertStringContainsString('Objective', (string) $material->content);

        $draft->refresh();
        $this->assertSame(AiContentDraft::STATUS_APPLIED, $draft->status);
        $this->assertNotNull($draft->applied_at);

        $this->assertSame(1, AuditLog::query()->where('action', 'ai_content_draft_applied')->count());
    }

    public function test_a_draft_can_be_discarded(): void
    {
        $ctx = $this->makeCourseContext();
        $draft = $this->readyDraft($ctx);

        $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.ai.drafts.discard', [$ctx['course'], $draft]))
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(AiContentDraft::STATUS_DISCARDED, $draft->status);
        $this->assertSame(0, CourseMaterial::query()->count());
    }

    public function test_a_lecturer_cannot_apply_another_lecturers_draft(): void
    {
        $ctx = $this->makeCourseContext();
        $draft = $this->readyDraft($ctx);

        $otherLecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
        ]);

        $this->actingAs($otherLecturer)
            ->post(route('lecturer.ai.drafts.apply', [$ctx['course'], $draft]))
            ->assertNotFound();

        $this->assertSame(0, CourseMaterial::query()->count());
    }

    public function test_student_cannot_queue_batch_drafts(): void
    {
        $ctx = $this->makeCourseContext();
        Queue::fake();

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
        ]);

        $this->actingAs($student)
            ->post(route('lecturer.ai.batch', $ctx['course']), [
                'items' => [['action' => 'draft', 'instruction' => 'Give me the answers']],
            ])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    /** @param array<string,\App\Models\Model> $ctx */
    private function readyDraft(array $ctx): AiContentDraft
    {
        return AiContentDraft::query()->create([
            'user_id' => $ctx['lecturer']->id,
            'university_id' => $ctx['university']->id,
            'course_id' => $ctx['course']->id,
            'feature' => 'lecturer_assistant',
            'action' => 'lesson_plan',
            'instruction' => 'Week 4 lesson plan on normalization',
            'content' => "Objective: understand 1NF.\n\nActivity: normalize a sample table.",
            'status' => AiContentDraft::STATUS_READY,
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array<string,\App\Models\Model>
     */
    private function makeCourseContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create([
            'university_id' => $university->id,
            'is_current' => true,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonths(4),
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        LecturerCourseAssignment::query()->create([
            'user_id' => $lecturer->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_coordinator' => true,
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'lecturer');
    }
}
