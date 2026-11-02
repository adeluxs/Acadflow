<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Contracts\Media\MalwareScannerInterface;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\PracticeQuiz;
use App\Models\Semester;
use App\Models\StudentStudySession;
use App\Models\StudyNote;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\Ai\PracticeQuizService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use App\Services\Student\StudySourceService;
use App\Services\Student\StudyWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectedStudentStudyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        SettingService::set('allowed_file_extensions', 'pdf,docx,txt', 'string');
    }

    public function test_material_to_explanation_to_practice_feedback_and_revision(): void
    {
        $ctx = $this->context();
        extract($ctx);
        $this->actingAs($student)->post(route('study.materials.start', $material))->assertRedirect();
        $this->actingAs($student)->post(route('study.materials.start', $material))->assertRedirect();
        $this->assertDatabaseCount('student_study_sessions', 1);
        $session = StudentStudySession::firstOrFail();
        $this->get(route('study.show', $session))->assertOk()->assertSee('Read the source')->assertSee('Repeating groups');

        $this->mock(AiManager::class, function ($mock) use ($student): void {
            $mock->shouldReceive('analyze')->once()->with('material_assistant', \Mockery::on(fn ($p) => str_contains($p['material']['authorized_text'], 'Repeating groups') && $p['material']['selected_passage'] === 'Repeating groups'
            ), \Mockery::on(fn ($u) => $u->id === $student->id), \Mockery::type('string'))
                ->andReturn($this->response('material_assistant', ['answer' => 'A group repeats when several values occupy one field. Try one value per row.']));
            $mock->shouldReceive('analyze')->once()->with('practice_generator', \Mockery::on(fn ($p) => str_contains($p['source_text'], 'Repeating groups') && str_contains($p['grounding'], 'only the supplied source')
            ), \Mockery::type(User::class), \Mockery::type('string'))->andReturn($this->response('practice_generator', ['questions' => $this->questions()]));
        });
        $this->post(route('study.explain', $session), ['question' => 'Explain repeating groups simply', 'selected_text' => 'Repeating groups'])
            ->assertRedirect(route('study.show', $session).'#explanation');
        $this->assertNotNull($session->fresh()->explained_at);
        $this->get(route('study.show', $session))->assertSee('A group repeats');
        $this->post(route('study.practice', $session))->assertRedirect();
        $quiz = PracticeQuiz::firstOrFail();
        $this->assertSame($session->id, $quiz->study_session_id);
        $this->assertSame('', $quiz->questions[1]['source_excerpt']);
        $material->update(['content' => $material->content.'<p>An updated source paragraph.</p>']);
        $this->get(route('study.show', $session))->assertSee('The source has changed');
        $this->get(route('practice-quizzes.show', $quiz))->assertOk()->assertDontSee('The source says one value per field.');
        $token = (string) Str::uuid();
        $submission = ['answers' => [0 => 'Atomic values', 1 => 'Wrong'], 'submission_token' => $token];
        $this->post(route('practice-quizzes.submit', $quiz), $submission)->assertRedirect();
        $this->post(route('practice-quizzes.submit', $quiz), $submission)->assertRedirect();
        $this->assertDatabaseCount('practice_attempts', 1);
        $this->assertDatabaseHas('practice_attempts', ['quiz_id' => $quiz->id, 'score' => 1, 'total' => 2]);
        $this->assertDatabaseHas('topic_mastery', ['user_id' => $student->id, 'mastery' => 50]);
        $this->assertSame(1, $quiz->fresh()->review_interval_days);
        $this->assertTrue($quiz->fresh()->next_review_at->isSameDay(now()->addDay()));
        $this->get(route('practice-quizzes.show', $quiz))->assertSee('Your feedback')->assertSee('Revisit this')->assertSee('The source says one value per field.')->assertSee('Back to my source and explanation');
        $this->travel(2)->days();
        $this->get(route('study.index'))->assertOk()->assertSee('Revise next')->assertSee($quiz->title);
        $this->get(route('dashboard'))->assertOk()->assertSee('One useful step forward.')->assertSee('Revise next');
    }

    public function test_successful_recall_spreads_revision_without_same_day_farming(): void
    {
        extract($this->context());
        $quiz = $this->quiz($student, $course);
        $this->mock(AiManager::class)->shouldNotReceive('analyze');
        $service = app(PracticeQuizService::class);
        $service->gradeAttempt($quiz, $student, ['Atomic values', 'Repeating groups']);
        $this->assertSame(3, $quiz->fresh()->review_interval_days);
        $service->gradeAttempt($quiz->fresh(), $student, ['Atomic values', 'Repeating groups']);
        $this->assertSame(3, $quiz->fresh()->review_interval_days);
        $this->travel(3)->days();
        $service->gradeAttempt($quiz->fresh(), $student, ['Atomic values', 'Repeating groups']);
        $this->assertSame(6, $quiz->fresh()->review_interval_days);
        $this->travel(6)->days();
        $service->gradeAttempt($quiz->fresh(), $student, ['', '']);
        $this->assertSame(1, $quiz->fresh()->review_interval_days);
    }

    public function test_private_pasted_notes_are_escaped_and_other_students_cannot_open_them(): void
    {
        extract($this->context());
        $this->actingAs($student)->post(route('study.notes.import'), ['course_id' => $course->id, 'title' => 'Private examples', 'content' => 'A useful explanation of normalization. <script>alert(1)</script>'])
            ->assertRedirect();
        $note = StudyNote::firstOrFail();
        $session = StudentStudySession::firstOrFail();
        $this->get(route('study.show', $session))->assertOk()->assertSee('Private examples')->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame('manual', $note->source);
        $intruder = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $student->university_id]);
        $this->actingAs($intruder)->get(route('study.show', $session))->assertNotFound();
        $this->post(route('study.notes.start', $note))->assertNotFound();
        $this->get(route('study.index'))->assertOk()->assertDontSee('Private examples');
    }

    public function test_file_import_scans_extracts_privately_and_removes_temporary_media(): void
    {
        extract($this->context());
        Storage::fake('local');
        $this->mock(MalwareScannerInterface::class)->shouldReceive('scan')->once()->andReturn(['status' => 'clean', 'provider' => 'test']);
        $this->actingAs($student)->post(route('study.notes.import'), ['course_id' => $course->id, 'title' => 'Imported normalization', 'file' => UploadedFile::fake()->createWithContent('notes.txt', 'First normal form requires atomic values in every field.')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertStringContainsString('atomic values', StudyNote::firstOrFail()->content);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_infected_or_ambiguous_uploads_never_create_notes(): void
    {
        extract($this->context());
        Storage::fake('local');
        $this->mock(MalwareScannerInterface::class)->shouldReceive('scan')->once()->andReturn(['status' => 'infected', 'provider' => 'test']);
        $data = ['course_id' => $course->id, 'title' => 'Rejected', 'file' => UploadedFile::fake()->createWithContent('notes.txt', 'First normal form requires atomic values in every field.')];
        $this->actingAs($student)->post(route('study.notes.import'), $data)->assertSessionHasErrors('file');
        $this->post(route('study.notes.import'), [...$data, 'content' => 'Another paragraph supplied at the same time.'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('study_notes', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_passage_must_come_from_the_source_and_hidden_materials_are_not_sent_to_ai(): void
    {
        extract($this->context());
        $session = $this->makeStudySession($student, $course, $material);
        $this->mock(AiManager::class)->shouldNotReceive('analyze');
        $this->actingAs($student)->post(route('study.explain', $session), ['question' => 'Explain this passage', 'selected_text' => 'This is not part of the authorized source.'])->assertSessionHasErrors('selected_text');
        $material->update(['is_visible' => false]);
        $this->post(route('study.practice', $session))->assertForbidden();
        $this->get(route('study.show', $session))->assertForbidden();
        $this->get(route('study.index'))->assertOk()->assertDontSee('Atomicity source');
        $this->assertDatabaseCount('practice_quizzes', 0);
    }

    public function test_revoked_membership_or_enrollment_removes_saved_sessions_and_revision(): void
    {
        extract($this->context());
        $session = $this->makeStudySession($student, $course, $material);
        $quiz = $this->quiz($student, $course, ['study_session_id' => $session->id, 'material_id' => $material->id, 'next_review_at' => now()->subDay()]);
        $this->actingAs($student)->get(route('study.index'))->assertOk()->assertSee('Atomicity source');
        $affiliation = UserInstitutionAffiliation::create(['user_id' => $student->id, 'university_id' => $student->university_id, 'department_id' => $student->department_id, 'affiliation_type' => 'student', 'status' => 'revoked']);
        $this->get(route('study.show', $session))->assertForbidden();
        $this->get(route('practice-quizzes.show', $quiz))->assertForbidden();
        $this->get(route('study.index'))->assertOk()->assertDontSee('Atomicity source')->assertDontSee($quiz->title);
        $affiliation->delete();
        Enrollment::where('user_id', $student->id)->update(['status' => 'dropped']);
        $this->get(route('study.show', $session))->assertForbidden();
        $this->get(route('practice-quizzes.show', $quiz))->assertForbidden();
    }

    public function test_failed_ai_preserves_saved_explanation_and_invalid_quizzes_have_no_fabricated_answers(): void
    {
        extract($this->context());
        $session = $this->makeStudySession($student, $course, $material);
        $session->update(['explanation' => 'Previously saved explanation.', 'explained_at' => now()]);
        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->with('material_assistant', \Mockery::any(), \Mockery::any(), \Mockery::any())->andReturn(new AiResponse(source: 'openrouter', feature: 'material_assistant', success: false, errorCode: 'AI_RATE_LIMITED'));
            $mock->shouldReceive('analyze')->once()->with('practice_generator', \Mockery::any(), \Mockery::any(), \Mockery::any())->andReturn($this->response('practice_generator', ['questions' => [['question' => ['invalid'], 'answer' => 'No'], ['question' => 'An ungradable question', 'answer' => '']]]));
        });
        $this->actingAs($student)->post(route('study.explain', $session), ['question' => 'Explain this passage'])->assertSessionHasErrors('question');
        $this->assertSame('Previously saved explanation.', $session->fresh()->explanation);
        $this->post(route('study.practice', $session))->assertRedirect();
        $quiz = PracticeQuiz::firstOrFail();
        $this->assertSame('failed', $quiz->status);
        $this->post(route('practice-quizzes.submit', $quiz), ['answers' => []])->assertUnprocessable();
        $this->assertDatabaseCount('practice_attempts', 0);
    }

    public function test_material_feature_disabled_keeps_private_notes_but_hides_material_sessions_and_quizzes(): void
    {
        extract($this->context());
        $session = $this->makeStudySession($student, $course, $material);
        $this->quiz($student, $course, ['material_id' => $material->id, 'next_review_at' => now()->subDay()]);
        $note = StudyNote::create(['user_id' => $student->id, 'course_id' => $course->id, 'title' => 'Keep my private note', 'content' => '<p>Private source content containing useful study examples.</p>', 'source' => 'manual']);
        $privateSession = StudentStudySession::create(['user_id' => $student->id, 'course_id' => $course->id, 'note_id' => $note->id, 'last_studied_at' => now()]);
        FeatureAccessService::setStatus('course_materials', FeatureAccessService::STATUS_DISABLED, $student->id);
        $this->actingAs($student)->get(route('study.show', $session))->assertForbidden();
        $this->get(route('study.show', $privateSession))->assertOk()->assertSee('Keep my private note');
        $today = app(StudyWorkspaceService::class)->today($student->fresh());
        $this->assertCount(1, $today['sessions']);
        $this->assertCount(0, $today['due']);
    }

    public function test_disabled_ai_stops_generation_before_source_extraction(): void
    {
        extract($this->context());
        $session = $this->makeStudySession($student, $course, $material);
        $this->mock(AiManager::class)->shouldNotReceive('analyze');
        $this->mock(StudySourceService::class)->shouldNotReceive('sessionText');
        $this->partialMock(AiRuntimeConfigService::class, function ($mock): void {
            $mock->shouldReceive('featureEnabled')->with('material_assistant', \Mockery::any())->andReturnFalse();
            $mock->shouldReceive('featureEnabled')->with('practice_generator', \Mockery::any())->andReturnFalse();
        });
        $this->actingAs($student)->postJson(route('study.explain', $session), ['question' => 'Explain this passage'])->assertStatus(503)->assertJsonPath('error_code', 'AI_FEATURE_DISABLED');
        $this->postJson(route('study.practice', $session))->assertStatus(503)->assertJsonPath('error_code', 'AI_FEATURE_DISABLED');
        $this->assertDatabaseCount('practice_quizzes', 0);
    }

    public function test_study_migration_can_resume_and_roll_back_without_removing_coursework(): void
    {
        extract($this->context());
        $migration = require database_path('migrations/2026_10_23_090000_connect_student_study_sessions.php');
        $migration->up();
        $migration->up();
        $migration->down();
        $this->assertFalse(Schema::hasTable('student_study_sessions'));
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('course_materials', ['id' => $material->id]);
        $migration->up();
        $this->assertTrue(Schema::hasIndex('practice_attempts', 'practice_user_completed_idx'));
        $this->assertTrue(Schema::hasColumn('practice_quizzes', 'next_review_at'));
    }

    public function test_today_queries_and_payload_stay_bounded_as_history_grows(): void
    {
        extract($this->context());
        $this->makeStudySession($student, $course, $material);
        $this->quiz($student, $course, ['next_review_at' => now()->subDay()]);
        $workspace = app(StudyWorkspaceService::class);
        $workspace->today($student); // Warm request-scoped settings, not result data.
        DB::enableQueryLog();
        DB::flushQueryLog();
        $workspace->today($student);
        $before = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 40; $i++) {
            $note = StudyNote::create(['user_id' => $student->id, 'course_id' => $course->id, 'title' => 'History '.$i, 'content' => str_repeat('Large private source content. ', 1000), 'source' => 'manual']);
            StudentStudySession::create(['user_id' => $student->id, 'course_id' => $course->id, 'note_id' => $note->id, 'last_studied_at' => now(), 'explanation' => str_repeat('Large saved explanation. ', 1000)]);
            $this->quiz($student, $course, ['next_review_at' => now()->subDay()]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $today = $workspace->today($student);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($before + 2, count($queries)); // Bounded eager note/course queries.
        $this->assertLessThanOrEqual(7, count($queries));
        $this->assertCount(3, $today['sessions']);
        $this->assertCount(5, $today['due']);
        foreach ($today['sessions'] as $saved) {
            $this->assertArrayNotHasKey('explanation', $saved->getAttributes());
            $this->assertArrayNotHasKey('content', $saved->note?->getAttributes() ?? []);
        }
        foreach ($today['due'] as $due) {
            $this->assertArrayNotHasKey('questions', $due->getAttributes());
        }
        $this->actingAs($student)->get(route('study.index'))->assertOk()->assertViewHas('notes', fn ($p) => $p->count() === 6);
    }

    private function response(string $feature, array $data): AiResponse
    {
        return new AiResponse(source: 'openrouter', feature: $feature, success: true, data: $data, provider: 'openrouter', model: 'test-model');
    }

    private function questions(): array
    {
        return [
            ['question' => 'What does 1NF require?', 'options' => ['Atomic values', 'Repeated columns'], 'answer' => 'Atomic values', 'explanation' => 'The source says one value per field.', 'source_excerpt' => 'Every field holds one atomic value.', 'difficulty' => 'easy'],
            ['question' => 'What does 1NF remove?', 'options' => ['Primary keys', 'Repeating groups'], 'answer' => 'Repeating groups', 'explanation' => 'Repeated values move to individual rows.', 'source_excerpt' => 'Invented source quote must be removed.', 'difficulty' => 'medium'],
        ];
    }

    private function makeStudySession(User $student, Course $course, CourseMaterial $material): StudentStudySession
    {
        return StudentStudySession::create(['user_id' => $student->id, 'course_id' => $course->id, 'material_id' => $material->id, 'last_studied_at' => now()]);
    }

    private function quiz(User $student, Course $course, array $extra = []): PracticeQuiz
    {
        return PracticeQuiz::create([...['user_id' => $student->id, 'course_id' => $course->id, 'topic' => 'Normalization', 'title' => 'Saved atomicity practice', 'status' => 'ready', 'questions' => $this->questions()], ...$extra]);
    }

    private function context(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $academicSession = AcademicSession::factory()->create(['university_id' => $university->id, 'is_current' => true, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $academicSession->id, 'is_active' => true, 'start_date' => now(), 'end_date' => now()->addMonths(4)]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $university->id, 'department_id' => $department->id]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer', 'university_id' => $student->university_id]);
        $material = CourseMaterial::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'uploaded_by' => $lecturer->id, 'title' => 'Atomicity source', 'type' => 'lecture_note', 'content' => '<p>First normal form removes Repeating groups.</p><p>Every field holds one atomic value.</p>', 'is_visible' => true, 'requires_enrollment' => true]);

        return compact('student', 'course', 'material', 'semester', 'department');
    }
}
