<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizDraw;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeReadAttempt;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\PublicationService;
use App\Services\Knowledge\QuizBankGenerator;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeAiQuizTest extends TestCase
{
    use RefreshDatabase;

    private string $body;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureAccessService::setStatus('knowledge_read_to_earn', 'enabled');
        SettingService::set('knowledge_read_earn_requires_pass', false, 'boolean');
        SettingService::set('knowledge_read_earn_min_seconds', 0, 'integer');
        SettingService::set('knowledge_read_earn_max_wpm', 100000, 'integer');
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
        $facts = [];
        foreach (range(1, 12) as $i) {
            $facts[] = "<p>Fact number {$i}: the campus library opens at {$i} o'clock for students reading chapter {$i} of the handbook.</p>";
        }
        $this->body = implode('', $facts).str_repeat('<p>Extra reading notes about study habits and planning for exams.</p>', 6);
    }

    /** Fake AI that answers with $count well-formed questions grounded in the guide (plus some bad ones). */
    private function fakeAi(int $count = 12, bool $success = true): void
    {
        $questions = [];
        foreach (range(1, $count) as $i) {
            $questions[] = ['question' => "When does the library open for chapter {$i}?", 'options' => ["{$i} o'clock", 'Midnight', 'Never', 'Noon'],
                'answer' => "{$i} o'clock", 'explanation' => "The guide says {$i} o'clock.", 'source_excerpt' => "the campus library opens at {$i} o'clock"];
        }
        // These must be thrown away: answer not an option, made-up excerpt, "all of the above".
        $questions[] = ['question' => 'Bad answer?', 'options' => ['a', 'b', 'c', 'd'], 'answer' => 'z', 'explanation' => '', 'source_excerpt' => 'the campus library opens'];
        $questions[] = ['question' => 'Invented fact?', 'options' => ['a', 'b', 'c', 'd'], 'answer' => 'a', 'explanation' => '', 'source_excerpt' => 'quantum gravity is taught in year one'];
        $questions[] = ['question' => 'Lazy options?', 'options' => ['a', 'b', 'c', 'All of the above'], 'answer' => 'a', 'explanation' => '', 'source_excerpt' => 'the campus library opens at 1'];

        $mock = \Mockery::mock(AiManager::class);
        $mock->shouldReceive('analyze')->andReturn(new AiResponse(source: 'test', feature: QuizBankGenerator::FEATURE, success: $success, data: $success ? ['questions' => $questions] : [], errorCode: $success ? null : 'AI_PROVIDER_ERROR'));
        $this->app->instance(AiManager::class, $mock);
    }

    private function guide(User $creator, bool $publish = true): KnowledgePublication
    {
        $this->actingAs($creator);
        $publication = app(PublicationService::class)->createDraft(['title' => 'Library guide', 'body' => $this->body,
            'publication_type_id' => DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id'), 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'x', 'ownership_declaration' => true], $creator);
        if ($publish) {
            app(\App\Services\Knowledge\ModerationService::class)->publish($publication->fresh(), User::factory()->create(['role' => 'super_admin']));
        }
        auth()->logout();

        return KnowledgePublication::withoutGlobalScopes()->findOrFail($publication->id);
    }

    public function test_publishing_writes_a_grounded_bank_and_each_reader_gets_a_shuffled_draw(): void
    {
        $this->fakeAi();
        $publication = $this->guide(User::factory()->create());

        $bank = KnowledgeQuizQuestion::query()->where('knowledge_publication_id', $publication->id)->get();
        $this->assertSame('ready', $publication->quiz_status);
        $this->assertGreaterThanOrEqual(6, $bank->count());
        $this->assertTrue($bank->every(fn ($q) => $q->source === 'ai' && $q->is_active && count($q->options) === 4));
        $this->assertFalse($bank->contains('question', 'Invented fact?'));
        $this->assertFalse($bank->contains('question', 'Lazy options?'));

        // Two readers get 5 questions each, saved so a refresh shows the same quiz.
        $draws = [];
        foreach (User::factory()->count(2)->create() as $reader) {
            $this->actingAs($reader)->get(route('knowledge.show', $publication))->assertOk()->assertSee('Submit answers');
            $first = KnowledgeQuizDraw::query()->where('user_id', $reader->id)->firstOrFail()->questions;
            $this->get(route('knowledge.show', $publication));
            $this->assertSame($first, KnowledgeQuizDraw::query()->where('user_id', $reader->id)->firstOrFail()->questions);
            $this->assertCount(5, $first);
            $draws[] = $first;
        }
        $this->assertTrue(collect($draws[0])->pluck('order')->contains(fn ($o) => $o !== [0, 1, 2, 3]) || collect($draws[1])->pluck('order')->contains(fn ($o) => $o !== [0, 1, 2, 3]), 'Options are shuffled');

        // Scoring maps the shown option back to the right answer.
        $reader = User::query()->whereKey(KnowledgeQuizDraw::query()->value('user_id'))->first();
        $this->actingAs($reader);
        $answers = [];
        foreach (app(\App\Services\Knowledge\ReadToEarnService::class)->drawFor($reader, $publication) as $question) {
            $answers[$question->id] = array_search($question->correct_option, $question->display_order, true);
        }
        $this->post(route('knowledge.rewards.quiz.submit', $publication), ['answers' => $answers])->assertSessionHasNoErrors();
        $attempt = KnowledgeReadAttempt::query()->where('user_id', $reader->id)->firstOrFail();
        $this->assertSame([100, 5], [(int) $attempt->score_percent, (int) $attempt->question_count]);
        $this->get(route('knowledge.show', $publication))->assertSee('You scored 100%');
    }

    public function test_answers_for_questions_not_shown_are_rejected(): void
    {
        $this->fakeAi();
        $publication = $this->guide(User::factory()->create());
        $reader = User::factory()->create();
        // Never opened the quiz: there is no draw, so the submission is refused.
        $all = KnowledgeQuizQuestion::query()->where('knowledge_publication_id', $publication->id)->pluck('correct_option', 'id')->all();
        $this->actingAs($reader)->post(route('knowledge.rewards.quiz.submit', $publication), ['answers' => array_slice($all, 0, 5, true)])->assertSessionHasErrors('answers');
        $this->assertSame(0, KnowledgeReadAttempt::query()->count());
    }

    public function test_reports_pull_a_question_and_admin_can_restore_it(): void
    {
        $this->fakeAi();
        SettingService::set('knowledge_quiz_report_limit', 2, 'integer');
        SettingService::flushRequestCache();
        $publication = $this->guide(User::factory()->create());
        $readers = User::factory()->count(3)->create();
        foreach ($readers as $reader) {
            $this->actingAs($reader)->get(route('knowledge.show', $publication));
        }
        // Make sure the first two readers were both shown the same question.
        $question = KnowledgeQuizQuestion::query()->where('knowledge_publication_id', $publication->id)->firstOrFail();
        foreach ($readers->take(2) as $reader) {
            $draw = KnowledgeQuizDraw::query()->where('user_id', $reader->id)->firstOrFail();
            $items = collect($draw->questions)->reject(fn ($i) => $i['id'] === $question->id)->take(4)->push(['id' => $question->id, 'order' => [0, 1, 2, 3]])->values()->all();
            $draw->update(['questions' => $items]);
        }

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('knowledge.rewards.quiz.report', $question), ['reason' => 'wrong_answer'])->assertForbidden();
        $this->actingAs($readers[0])->post(route('knowledge.rewards.quiz.report', $question), ['reason' => 'wrong_answer'])->assertSessionHasNoErrors();
        $this->post(route('knowledge.rewards.quiz.report', $question), ['reason' => 'wrong_answer']);
        $this->assertSame(1, $question->fresh()->report_count);
        $this->assertNull($question->fresh()->retired_at);
        $this->actingAs($readers[1])->post(route('knowledge.rewards.quiz.report', $question), ['reason' => 'unclear']);
        $this->assertNotNull($question->fresh()->retired_at);
        $this->assertFalse(app(\App\Services\Knowledge\ReadToEarnService::class)->questions($publication)->contains('id', $question->id));

        // Reader 0's saved quiz contained the pulled question, so they get a fresh one without it.
        $this->actingAs($readers[0])->get(route('knowledge.show', $publication));
        $this->assertFalse(collect(KnowledgeQuizDraw::query()->where('user_id', $readers[0]->id)->first()->questions)->contains('id', $question->id));

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.knowledge.read-earn.index'))->assertOk()->assertSee($question->question);
        $this->post(route('admin.knowledge.read-earn.questions.review', $question), ['decision' => 'restore'])->assertSessionHasNoErrors();
        $this->assertSame([0, null], [$question->fresh()->report_count, $question->fresh()->retired_at]);
    }

    public function test_creator_questions_are_kept_and_failed_or_approval_states_work(): void
    {
        $creator = User::factory()->create();
        $this->fakeAi(12, false);
        $publication = $this->guide($creator);
        $this->assertSame(['failed', 1], [$publication->quiz_status, (int) $publication->quiz_attempts]);
        $this->assertSame(0, KnowledgeQuizQuestion::query()->count());

        // Retry from the hourly sweep, now with approval required.
        SettingService::set('knowledge_quiz_ai_requires_approval', true, 'boolean');
        SettingService::flushRequestCache();
        $this->fakeAi();
        DB::table('knowledge_publications')->where('id', $publication->id)->update(['quiz_source_hash' => null]);
        $this->artisan('knowledge:generate-quizzes', ['--now' => true])->assertSuccessful();
        $publication->refresh();
        $this->assertSame('awaiting_approval', $publication->quiz_status);
        $this->assertSame(0, KnowledgeQuizQuestion::query()->where('is_active', true)->count());

        // The creator sees their bank, saves it (approves) and adds one of their own.
        $this->actingAs($creator)->get(route('knowledge.rewards.questions.edit', $publication))->assertOk()->assertSee('waiting for your approval')->assertSee('AI');
        $rows = KnowledgeQuizQuestion::query()->where('knowledge_publication_id', $publication->id)->get()->map(fn ($q) => ['question' => $q->question, 'options' => $q->options, 'correct' => $q->correct_option, 'source' => 'ai'])->all();
        $rows[] = ['question' => 'My own question?', 'options' => ['Yes', 'No'], 'correct' => 0, 'source' => 'creator'];
        $this->put(route('knowledge.rewards.questions.update', $publication), ['questions' => $rows])->assertSessionHasNoErrors();
        $this->assertSame('ready', $publication->fresh()->quiz_status);
        $this->assertSame(1, KnowledgeQuizQuestion::query()->where('source', 'creator')->count());

        // Regenerating replaces only the AI questions.
        SettingService::set('knowledge_quiz_ai_requires_approval', false, 'boolean');
        SettingService::flushRequestCache();
        $this->post(route('knowledge.rewards.questions.regenerate', $publication))->assertSessionHasNoErrors();
        $this->assertSame(1, KnowledgeQuizQuestion::query()->where('source', 'creator')->where('question', 'My own question?')->count());
        $this->assertGreaterThan(1, KnowledgeQuizQuestion::query()->where('source', 'ai')->where('is_active', true)->count());
    }

    public function test_switching_ai_off_leaves_creators_in_charge(): void
    {
        SettingService::set('knowledge_quiz_ai_enabled', false, 'boolean');
        SettingService::flushRequestCache();
        $mock = \Mockery::mock(AiManager::class);
        $mock->shouldNotReceive('analyze');
        $this->app->instance(AiManager::class, $mock);
        $creator = User::factory()->create();
        $publication = $this->guide($creator);
        $this->assertNull($publication->quiz_status);
        $this->actingAs($creator)->get(route('knowledge.show', $publication))->assertSee('Add quiz questions so readers can earn');
    }
}
