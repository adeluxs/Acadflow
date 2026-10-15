<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\StudentLearningProfile;
use App\Models\StudyNote;
use App\Models\University;
use App\Models\User;
use App\Services\Ai\AcademicAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProfileAndStudyNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_learning_preferences(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['student'])
            ->put(route('student.learning-profile.update'), [
                'level' => 'beginner',
                'pace' => 'slow',
                'explanation_style' => 'simple',
                'goals' => 'Pass my exams',
            ])
            ->assertRedirect();

        $profile = StudentLearningProfile::query()->where('user_id', $ctx['student']->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('beginner', $profile->level);
        $this->assertContains('Preferred level: beginner', $profile->summaryLines());
    }

    public function test_learning_profile_is_injected_into_the_study_assistant_payload(): void
    {
        $ctx = $this->makeContext();
        StudentLearningProfile::query()->create(['user_id' => $ctx['student']->id, 'level' => 'beginner']);

        $captured = null;
        $this->mock(AiManager::class, function ($mock) use (&$captured): void {
            $mock->shouldReceive('analyze')->andReturnUsing(function ($feature, $payload) use (&$captured) {
                $captured = $payload;
                return new AiResponse(source: 'openrouter', feature: $feature, success: true, data: ['answer' => 'ok'], provider: 'openrouter', model: 'm');
            });
        });

        $result = app(AcademicAssistantService::class)->ask(
            $ctx['student'],
            'Explain database normalization with a simple example',
            $ctx['course']->id,
            null,
            null,
            null,
            'socratic',
        );

        $this->assertSame('socratic', $result['study_mode']);
        $this->assertContains('Preferred level: beginner', $captured['learning_profile']);
        $this->assertStringContainsString('Socratic', $captured['instruction']);
        $this->assertStringContainsString('Personalize to the learner profile', $captured['instruction']);
    }

    public function test_student_can_save_a_study_note_from_an_answer(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['student'])
            ->postJson(route('study-notes.store'), [
                'title' => 'Normalization basics',
                'content' => "First normal form removes repeating groups.\n\nSecond normal form removes partial dependencies.",
                'course_id' => $ctx['course']->id,
                'source' => 'ai_answer',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $note = StudyNote::query()->where('user_id', $ctx['student']->id)->first();
        $this->assertNotNull($note);
        $this->assertSame('ai_answer', $note->source);
        $this->assertStringContainsString('First normal form', (string) $note->content);
    }

    public function test_note_content_is_sanitized(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['student'])
            ->post(route('study-notes.store'), [
                'title' => 'Risky note',
                'content' => 'Read this <script>alert(1)</script>',
            ])
            ->assertRedirect();

        $note = StudyNote::query()->where('user_id', $ctx['student']->id)->firstOrFail();
        $this->assertStringNotContainsString('<script', (string) $note->content);
    }

    public function test_student_cannot_save_a_note_for_an_inaccessible_course(): void
    {
        $ctx = $this->makeContext();

        $otherUniversity = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $otherUniversity->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $foreignCourse = Course::factory()->create(['department_id' => $department->id]);

        $this->actingAs($ctx['student'])
            ->post(route('study-notes.store'), [
                'title' => 'Foreign',
                'content' => 'Nope',
                'course_id' => $foreignCourse->id,
            ])
            ->assertForbidden();
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
