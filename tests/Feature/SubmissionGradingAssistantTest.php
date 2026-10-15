<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionGradingAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_grading_suggestion_is_advisory_and_does_not_write_a_grade(): void
    {
        $ctx = $this->makeCourseContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
        ]);
        $submission = Submission::factory()->create([
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'type' => 'assignment',
            'title' => 'Database normalization essay',
        ]);

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')
                ->once()
                ->andReturn(new AiResponse(
                    source: 'openrouter',
                    feature: 'submission_validator',
                    success: true,
                    data: [
                        'score' => 78,
                        'feedback' => 'Good structure; expand the discussion of 3NF.',
                        'summary' => 'Solid work with room to grow.',
                    ],
                    provider: 'openrouter',
                    model: 'google/gemini-2.5-flash',
                    confidence: 0.72,
                ));
        });

        $this->actingAs($ctx['lecturer'])
            ->postJson(route('submissions.ai.grade-suggestion', $submission))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'requires_confirmation' => true,
            ])
            ->assertJsonPath('suggested_score', 78);

        // The advisory assistant must never create or change a grade.
        $this->assertSame(0, SubmissionGrade::query()->count());
    }

    public function test_unauthorized_user_cannot_request_a_grading_suggestion(): void
    {
        $ctx = $this->makeCourseContext();
        $outsider = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $ctx['university']->id,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
        ]);
        $submission = Submission::factory()->create([
            'user_id' => $student->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
        ]);

        $this->actingAs($outsider)
            ->postJson(route('submissions.ai.grade-suggestion', $submission))
            ->assertForbidden();
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
