<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Jobs\GenerateStudyPlan;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\FlashcardDeck;
use App\Models\PracticeQuiz;
use App\Models\Semester;
use App\Models\StudyPlan;
use App\Models\University;
use App\Models\User;
use App\Services\Ai\FlashcardService;
use App\Services\Ai\PracticeQuizService;
use App\Services\Ai\StudyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentStudyToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_practice_quiz_generation_and_grading(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'practice_generator',
                success: true,
                data: ['questions' => [[
                    'question' => 'What does 1NF remove?',
                    'options' => ['Repeating groups', 'Primary keys'],
                    'answer' => 'Repeating groups',
                    'explanation' => '1NF removes repeating groups.',
                    'difficulty' => 'easy',
                ]]],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $service = app(PracticeQuizService::class);
        $quiz = $service->generate($ctx['student'], $ctx['course'], 'Normalization', null, 1);

        $this->assertSame(1, $quiz->questionCount());
        $this->assertSame('What does 1NF remove?', $quiz->questions[0]['question']);

        $attempt = $service->gradeAttempt($quiz, $ctx['student'], [0 => 'Repeating groups']);
        $this->assertSame(1, $attempt->score);
        $this->assertSame(1, $attempt->total);
    }

    public function test_referral_quiz_activity_requires_three_answers_and_retries_do_not_add_days(): void
    {
        $ctx = $this->makeContext();
        $user = $ctx['student'];
        $application = \App\Models\AmbassadorApplication::create([
            'user_id' => User::factory()->create()->id, 'status' => 'approved', 'institution' => 'Campus',
            'department' => 'Computer Science', 'study_level' => '200', 'leadership_role' => 'course_rep',
            'phone' => '+2348000000000', 'community_size' => 50, 'statement' => 'Help classmates study effectively.', 'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['has_ambassador_referral' => true])->save();
        \App\Models\AmbassadorReferral::create([
            'ambassador_application_id' => $application->id, 'user_id' => $user->id,
            'registered_at' => now(), 'retention_starts_at' => now()->addDays(7), 'retention_ends_at' => now()->addDays(30),
        ]);
        $quiz = PracticeQuiz::create([
            'user_id' => $user->id, 'course_id' => $ctx['course']->id, 'title' => 'Practice quiz', 'status' => 'ready',
            'questions' => array_fill(0, 3, ['question' => 'Which database normal form eliminates repeating groups from a table?', 'answer' => 'First normal form']),
        ]);
        $service = app(PracticeQuizService::class);
        $service->gradeAttempt($quiz, $user, [0 => 'First normal form', 1 => 'First normal form']);
        $this->assertDatabaseCount('ambassador_referral_days', 0);
        $token = (string) \Illuminate\Support\Str::uuid();
        $service->gradeAttempt($quiz, $user, array_fill(0, 3, 'First normal form'), $token);
        $this->assertDatabaseCount('ambassador_referral_days', 1);
        $this->travel(1)->days();
        $service->gradeAttempt($quiz, $user, array_fill(0, 3, 'First normal form'), $token);
        $this->assertDatabaseCount('ambassador_referral_days', 1);
        $this->mock(\App\Services\Student\ReflectionCoachService::class, function ($mock): void {
            $mock->shouldReceive('feedback')->once()->andThrow(new \RuntimeException('Optional AI unavailable'));
        });
        $this->actingAs($user)->post(route('reflections.store'), [
            'reflection' => 'Today I improved my understanding of how database normalization removes repeating groups and why it makes updates easier to manage.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ambassador_referral_days', 2);
    }

    public function test_flashcard_generation_and_review_rescheduling(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'flashcard_generator',
                success: true,
                data: ['cards' => [['front' => '1NF', 'back' => 'No repeating groups']]],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $service = app(FlashcardService::class);
        $deck = $service->generate($ctx['student'], $ctx['course'], 'Normalization');

        $this->assertSame(1, $deck->cardCount());

        $card = $service->review($deck, $ctx['student'], 0, 5);
        $this->assertSame(1, $card['repetitions']);
        $this->assertSame(1, $card['interval_days']);
        $this->assertNotNull($card['due_at']);
    }

    public function test_study_plan_generation_stores_items(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'study_planner',
                success: true,
                data: ['items' => [[
                    'topic' => 'Functional dependencies',
                    'objective' => 'Understand FD',
                    'activity' => 'Read and practise',
                    'estimated_minutes' => 45,
                ]]],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $plan = app(StudyPlanService::class)->generate($ctx['student'], $ctx['course'], 4);

        $this->assertSame(StudyPlan::STATUS_READY, $plan->status);
        $this->assertSame(1, $plan->itemCount());
        $this->assertSame('Functional dependencies', $plan->items[0]['topic']);
    }

    public function test_queued_study_plan_job_generates_into_a_pending_plan(): void
    {
        $ctx = $this->makeContext();

        $this->mock(AiManager::class, function ($mock): void {
            $mock->shouldReceive('analyze')->once()->andReturn(new AiResponse(
                source: 'openrouter',
                feature: 'study_planner',
                success: true,
                data: ['items' => [['topic' => 'Indexing', 'objective' => 'x', 'activity' => 'y', 'estimated_minutes' => 30]]],
                provider: 'openrouter',
                model: 'm',
            ));
        });

        $service = app(StudyPlanService::class);
        $plan = $service->queue($ctx['student'], $ctx['course'], 4);
        $this->assertSame(StudyPlan::STATUS_PENDING, $plan->status);

        (new GenerateStudyPlan($plan->id))->handle(app(StudyPlanService::class));

        $plan->refresh();
        $this->assertSame(StudyPlan::STATUS_READY, $plan->status);
        $this->assertSame(1, $plan->itemCount());
    }

    public function test_another_student_cannot_open_someone_elses_quiz(): void
    {
        $ctx = $this->makeContext();

        $quiz = PracticeQuiz::query()->create([
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'title' => 'Private quiz',
            'status' => PracticeQuiz::STATUS_READY,
            'questions' => [],
        ]);

        $intruder = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $ctx['university']->id]);

        $this->actingAs($intruder)
            ->get(route('practice-quizzes.show', $quiz))
            ->assertNotFound();
    }

    public function test_flashcard_review_endpoint_records_a_rating_sent_as_a_form_field(): void
    {
        $ctx = $this->makeContext();
        $deck = $this->makeDeck($ctx);

        // Mirrors the deck view: the rating is posted as an explicit field, so
        // it survives duplicate-submit button disabling.
        $this->actingAs($ctx['student'])
            ->post(route('flashcards.review', $deck), ['card_index' => 0, 'rating' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('flashcard_reviews', [
            'deck_id' => $deck->id,
            'user_id' => $ctx['student']->id,
            'card_index' => 0,
            'rating' => 4,
        ]);
        $this->assertSame(1, $deck->fresh()->cards[0]['repetitions']);
    }

    public function test_flashcard_review_requires_a_rating(): void
    {
        $ctx = $this->makeContext();
        $deck = $this->makeDeck($ctx);

        $this->actingAs($ctx['student'])
            ->post(route('flashcards.review', $deck), ['card_index' => 0])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('flashcard_reviews', ['deck_id' => $deck->id]);
    }

    public function test_flashcard_deck_gates_the_answer_behind_an_attempt(): void
    {
        $ctx = $this->makeContext();
        $deck = $this->makeDeck($ctx);

        $this->actingAs($ctx['student'])
            ->get(route('flashcards.show', $deck))
            ->assertOk()
            ->assertViewIs('student.flashcard-deck')
            ->assertSee('attempt it first')
            ->assertSee('data-answer', false)
            ->assertSee('data-reveal', false)
            ->assertSee('No repeating groups');
    }

    public function test_study_tool_pages_show_student_guidance(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['student'])->get(route('flashcards.index'))
            ->assertOk()->assertSee('How to use flashcards');

        $this->actingAs($ctx['student'])->get(route('practice-quizzes.index'))
            ->assertOk()->assertSee('How to use practice quizzes');

        $this->actingAs($ctx['student'])->get(route('study-plans.index'))
            ->assertOk()->assertSee('How to use study plans');

        $this->actingAs($ctx['student'])->get(route('reflections.index'))
            ->assertOk()->assertSee('How to use the reflection journal');

        $this->actingAs($ctx['student'])->get(route('mastery.index'))
            ->assertOk()->assertSee('How to read this page');

        $this->actingAs($ctx['student'])->get(route('deadline-coach.index'))
            ->assertOk()->assertSee('How to use the deadline coach');

        $this->actingAs($ctx['student'])->get(route('student.learning-profile'))
            ->assertOk()->assertSee('How these preferences are used');
    }

    public function test_study_tools_link_back_to_the_ai_assistant(): void
    {
        $ctx = $this->makeContext();
        $deck = $this->makeDeck($ctx);

        $urls = [
            route('flashcards.index'),
            route('flashcards.show', $deck),
            route('practice-quizzes.index'),
            route('study-plans.index'),
            route('mastery.index'),
            route('deadline-coach.index'),
            route('reflections.index'),
            route('study-notes.index'),
            route('student.learning-profile'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($ctx['student'])
                ->get($url)
                ->assertOk()
                ->assertSee('Back to AI Assistant');
        }
    }

    /**
     * @param  array<string,\App\Models\Model>  $ctx
     */
    private function makeDeck(array $ctx): FlashcardDeck
    {
        return FlashcardDeck::query()->create([
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'title' => 'Normalization',
            'cards' => [[
                'front' => '1NF',
                'back' => 'No repeating groups',
                'ease_factor' => 2.5,
                'interval_days' => 0,
                'repetitions' => 0,
                'due_at' => now()->toIso8601String(),
                'last_reviewed_at' => null,
            ]],
        ]);
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
