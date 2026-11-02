<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\DeadlineCoachPlan;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\MaterialAnnotation;
use App\Models\PracticeQuiz;
use App\Models\ReadinessSnapshot;
use App\Models\SelfAssessment;
use App\Models\Semester;
use App\Models\StudyReflection;
use App\Models\SubmissionTask;
use App\Models\TopicMastery;
use App\Models\University;
use App\Models\User;
use App\Services\Student\ReadinessService;
use App\Services\Student\StudentMasteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mastery_and_readiness_are_computed_from_practice(): void
    {
        $ctx = $this->makeContext();

        $quiz = PracticeQuiz::query()->create([
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'title' => 'Normalization practice',
            'topic' => 'Normalization',
            'status' => PracticeQuiz::STATUS_READY,
            'questions' => [],
        ]);
        $quiz->attempts()->create([
            'user_id' => $ctx['student']->id,
            'score' => 1,
            'total' => 2,
            'answers' => [
                ['selected' => 'A', 'is_correct' => true],
                ['selected' => 'B', 'is_correct' => false],
            ],
        ]);

        $mastery = app(StudentMasteryService::class)->recompute($ctx['student'], $ctx['course']);
        $this->assertCount(1, $mastery);
        $this->assertSame(50, TopicMastery::query()->first()->mastery);

        $snapshot = app(ReadinessService::class)->compute($ctx['student'], $ctx['course']);
        $this->assertInstanceOf(ReadinessSnapshot::class, $snapshot);
        $this->assertSame(40, $snapshot->score);
    }

    public function test_self_assessment_is_saved(): void
    {
        $ctx = $this->makeContext();
        $task = SubmissionTask::factory()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'created_by' => $ctx['student']->id,
            'status' => 'published',
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('self-assessment.store', [$ctx['course'], $task]), [
                'checklist' => ['Clarity', 'Referencing'],
                'confidence' => 70,
                'notes' => 'Add more examples.',
            ])
            ->assertRedirect();

        $assessment = SelfAssessment::query()->first();
        $this->assertNotNull($assessment);
        $this->assertSame(70, $assessment->confidence);
        $this->assertContains('Clarity', $assessment->checklist);
    }

    public function test_reflection_captures_ai_feedback(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'reflection_coach',
                success: true,
                data: ['feedback' => 'Good spaced practice; try shorter daily sessions.'],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $this->actingAs($ctx['student'])
            ->post(route('reflections.store'), [
                'reflection' => 'I studied for two hours but got distracted near the end.',
            ])
            ->assertRedirect();

        $reflection = StudyReflection::query()->first();
        $this->assertNotNull($reflection);
        $this->assertStringContainsString('spaced practice', (string) $reflection->ai_feedback);
    }

    public function test_annotations_can_be_saved_and_summarized(): void
    {
        $ctx = $this->makeContext();
        $material = CourseMaterial::query()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'uploaded_by' => $ctx['student']->id,
            'title' => 'Normalization notes',
            'type' => 'lecture_note',
            'content' => '<p>Body</p>',
            'content_format' => 'html',
            'source' => 'authored',
            'requires_enrollment' => true,
            'is_visible' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->post(route('annotations.store', [$ctx['course'], $material]), ['note' => 'Key idea: remove partial dependencies.'])
            ->assertRedirect();

        $this->assertSame(1, MaterialAnnotation::query()->count());

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'annotation_summary',
                success: true,
                data: ['summary' => 'Normalization removes redundancy.'],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $this->actingAs($ctx['student'])
            ->postJson(route('annotations.summary', [$ctx['course'], $material]))
            ->assertOk()
            ->assertJson(['summary' => 'Normalization removes redundancy.']);
    }

    public function test_deadline_coach_generates_an_action_plan(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'study_planner',
                success: true,
                data: [
                    'items' => [['title' => 'Submit assignment', 'due' => null, 'steps' => ['Draft outline', 'Write body']]],
                    'summary' => 'Start with the earliest deadline.',
                ],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $this->actingAs($ctx['student'])
            ->post(route('deadline-coach.store'), ['course_id' => $ctx['course']->id])
            ->assertRedirect();

        $plan = DeadlineCoachPlan::query()->first();
        $this->assertNotNull($plan);
        $this->assertCount(1, $plan->items);
        $this->assertSame('Submit assignment', $plan->items[0]['title']);
    }

    /**
     * @return array<string,\App\Models\Model>
     */
    private function makeContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_current' => true, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'is_active' => true, 'start_date' => now(), 'end_date' => now()->addMonths(4)]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $university->id, 'department_id' => $department->id]);

        Enrollment::query()->create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'student');
    }
}
