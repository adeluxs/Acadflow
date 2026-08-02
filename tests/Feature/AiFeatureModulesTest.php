<?php

namespace Tests\Feature;

use App\Ai\Features\CitationAssistantModule;
use App\Ai\Features\PlagiarismModule;
use App\Ai\Features\SubmissionValidatorModule;
use App\Ai\Features\WritingAssistantModule;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiFeatureModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeSubmission(string $type, string $text): Submission
    {
        $student = User::factory()->create();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $submission = Submission::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'type' => $type,
            'title' => 'Test',
            'status' => 'submitted',
            'submitted_at' => now(),
            'uuid' => \Illuminate\Support\Str::uuid(),
        ]);

        $path = "submissions/{$submission->uuid}/doc.txt";
        Storage::put($path, $text);
        SubmissionVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'file_name' => 'doc.txt',
            'file_path' => $path,
            'file_size' => strlen($text),
            'mime_type' => 'text/plain',
            'uploaded_by' => $student->id,
            'is_current' => true,
        ]);

        return $submission;
    }

    public function test_submission_validator_produces_report(): void
    {
        $submission = $this->makeSubmission('project', 'short text only');

        $module = new SubmissionValidatorModule(
            app(\App\Ai\AiManager::class),
            app(\App\Ai\Support\TextExtractor::class)
        );

        $response = $module->validate($submission);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->score);
        $this->assertIsArray($response->issues);
    }

    public function test_plagiarism_module_runs_against_corpus(): void
    {
        $submission = $this->makeSubmission('assignment', 'Introduction with proper analysis. Conclusion wraps up. References listed.');

        $module = new PlagiarismModule(app(\App\Ai\AiManager::class));

        $response = $module->analyze($submission);

        $this->assertTrue($response->success);
        $this->assertArrayHasKey('issues', $response->toArray());
    }

    public function test_writing_assistant_returns_suggestions(): void
    {
        $module = new WritingAssistantModule(
            app(\App\Ai\AiManager::class),
            app(\App\Ai\Support\TextExtractor::class)
        );

        $response = $module->analyze('The thing is really very bad and stuff.', 'assignment');

        $this->assertTrue($response->success);
        $this->assertNotEmpty($response->issues);
    }

    public function test_citation_assistant_detects_missing_reference_list(): void
    {
        $module = new CitationAssistantModule(app(\App\Ai\AiManager::class));

        $response = $module->analyze('An essay about topics without any citation support.', 'apa');

        $this->assertTrue($response->success);
        $codes = array_column($response->issues, 'code');
        $this->assertContains('missing_reference_list', $codes);
    }

    public function test_ai_analysis_event_dispatches_job(): void
    {
        \Bus::fake();

        $submission = $this->makeSubmission('assignment', 'Introduction and conclusion with references here.');

        event(new \App\Events\SubmissionAiAnalysisRequested($submission, $submission->user));

        \Bus::assertDispatched(\App\Jobs\ProcessSubmissionAiAnalysis::class);
    }
}
