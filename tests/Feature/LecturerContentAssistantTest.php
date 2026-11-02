<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LecturerContentAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_ai_content_endpoint_returns_a_draft_and_persists_nothing(): void
    {
        $ctx = $this->makeCourseContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')
                ->once()
                ->andReturn(new AiResponse(
                    source: 'openrouter',
                    feature: 'lecturer_assistant',
                    success: true,
                    data: ['answer' => 'Draft lesson plan for week 4.'],
                    summary: 'ok',
                    provider: 'openrouter',
                    model: 'google/gemini-2.5-flash',
                ));
        });

        $before = CourseMaterial::query()->count();

        $this->actingAs($ctx['lecturer'])
            ->postJson(route('lecturer.ai.content', $ctx['course']), [
                'action' => 'lesson_plan',
                'instruction' => 'Create a week 4 lesson plan on database normalization.',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'answer' => 'Draft lesson plan for week 4.',
                'requires_confirmation' => true,
            ]);

        $this->assertSame($before, CourseMaterial::query()->count());
    }

    public function test_material_scoped_ai_endpoint_requires_material_ownership(): void
    {
        $ctx = $this->makeCourseContext();

        // Material uploaded by a different lecturer.
        $otherLecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
        ]);
        $material = CourseMaterial::query()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'uploaded_by' => $otherLecturer->id,
            'title' => 'Someone else material',
            'type' => 'lecture_note',
            'content' => '<p>Body</p>',
            'content_format' => 'html',
            'source' => 'authored',
            'requires_enrollment' => true,
            'is_visible' => true,
        ]);

        $this->actingAs($ctx['lecturer'])
            ->postJson(route('lecturer.ai.material', [$ctx['course'], $material]), [
                'action' => 'summary',
                'instruction' => 'Summarize this material.',
            ])
            ->assertForbidden();
    }

    public function test_ai_content_endpoint_validates_the_instruction(): void
    {
        $ctx = $this->makeCourseContext();

        $this->actingAs($ctx['lecturer'])
            ->postJson(route('lecturer.ai.content', $ctx['course']), [
                'action' => 'draft',
                'instruction' => 'x',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instruction');
    }

    public function test_student_cannot_reach_lecturer_ai_content_endpoint(): void
    {
        $ctx = $this->makeCourseContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('lecturer.ai.content', $ctx['course']), [
                'action' => 'draft',
                'instruction' => 'Give me the answers',
            ])
            ->assertForbidden();
    }

    public function test_lecturer_service_humanizes_a_json_draft_answer(): void
    {
        $ctx = $this->makeCourseContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'lecturer_assistant',
                success: true,
                data: ['answer' => json_encode([
                    'status' => 'draft',
                    'lesson_plan' => ['topic' => 'Introduction to Computers'],
                ])],
                provider: 'openrouter',
                model: 'google/gemini-2.5-flash',
            ));
        });

        $response = $this->actingAs($ctx['lecturer'])
            ->postJson(route('lecturer.ai.content', $ctx['course']), [
                'action' => 'lesson_plan',
                'instruction' => 'Week 1 lesson on introduction to computers',
            ])
            ->assertOk();

        $answer = (string) $response->json('answer');
        $this->assertStringContainsString('Introduction to Computers', $answer);
        $this->assertStringNotContainsString('{', $answer);
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
