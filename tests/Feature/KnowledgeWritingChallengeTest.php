<?php

namespace Tests\Feature;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeRewardPayout;
use App\Models\KnowledgeWritingChallenge;
use App\Models\KnowledgeWritingEntry;
use App\Models\Notification;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KnowledgeWritingChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['notifications', 'knowledge_read_to_earn', 'academic_challenges'] as $feature) {
            FeatureAccessService::setStatus($feature, 'enabled');
        }
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function guide(User $creator, string $title, int $words = 900, int $questions = 3, bool $published = true): KnowledgePublication
    {
        $this->actingAs($creator);
        $type = DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id');
        $publication = app(PublicationService::class)->createDraft(['title' => $title, 'body' => '<p>'.str_repeat('word ', 20).'</p>',
            'publication_type_id' => $type, 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'Summary', 'ownership_declaration' => true], $creator);
        auth()->logout();
        DB::table('content_documents')->where('id', $publication->content_document_id)->update(['word_count' => $words]);
        if ($published) {
            KnowledgePublication::withoutGlobalScopes()->whereKey($publication->id)->update(['status' => 'published', 'published_at' => now()]);
        }
        for ($i = 0; $i < $questions; $i++) {
            KnowledgeQuizQuestion::create(['knowledge_publication_id' => $publication->id, 'question' => 'Q'.$i, 'options' => ['a', 'b'], 'correct_option' => 0, 'sort_order' => $i, 'is_active' => true, 'created_by' => $creator->id]);
        }

        return $publication->fresh();
    }

    private function createChallenge(User $admin): KnowledgeWritingChallenge
    {
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.writing.store'), [
            'title' => 'AcadSyntra Writing Challenge: November', 'theme' => 'Survive your first semester',
            'starts_at' => now()->subHour()->format('Y-m-d H:i'), 'submission_deadline' => now()->addDays(14)->format('Y-m-d H:i'),
            'results_at' => now()->addDays(21)->format('Y-m-d H:i'), 'prizes' => '50000, 30000, 20000', 'track_prize' => '',
            'min_words' => 600, 'min_questions' => 3, 'judge_weight' => 70, 'finalists' => 10, 'status' => 'published',
        ])->assertSessionHasNoErrors();

        return KnowledgeWritingChallenge::query()->where('title', 'AcadSyntra Writing Challenge: November')->firstOrFail();
    }

    public function test_admin_creates_an_official_challenge_and_it_shows_across_the_hub(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $challenge = $this->createChallenge($admin);
        $this->assertSame([5000000, 3000000, 2000000], array_map('intval', $challenge->prizes));
        $this->assertSame(10000000, $challenge->prizePoolMinor());
        $this->assertSame('open', $challenge->phase());
        $this->get(route('admin.knowledge.read-earn.index'))->assertOk()->assertSee('AcadSyntra Writing Challenge: November');
        $this->get(route('admin.knowledge.read-earn.writing.show', $challenge))->assertOk();

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('knowledge.writing.index'))->assertOk()->assertSee('AcadSyntra Writing Challenge: November');
        $this->get(route('knowledge.writing.show', $challenge))->assertOk()->assertSee('₦100,000');
        $this->get(route('knowledge.rewards.index'))->assertSee('AcadSyntra Writing Challenge: November');
        $this->get(route('knowledge.challenges.index'))->assertOk()->assertSee('Official · run and paid by AcadSyntra');
    }

    public function test_only_eligible_own_new_guides_can_enter_once(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $challenge = $this->createChallenge($admin);
        $student = User::factory()->create();
        $other = User::factory()->create();
        $short = $this->guide($student, 'Too short', 200);
        $noQuiz = $this->guide($student, 'No quiz', 900, 0);
        $good = $this->guide($student, 'How to read for exams');
        $theirs = $this->guide($other, 'Not mine');

        $this->actingAs($student);
        $this->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $theirs->id])->assertSessionHasErrors('publication');
        $this->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $short->id])->assertSessionHasErrors('publication');
        $this->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $noQuiz->id])->assertSessionHasErrors('publication');
        $this->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $good->id])->assertSessionHasNoErrors();
        $this->assertSame(1, KnowledgeWritingEntry::query()->where('user_id', $student->id)->count());
        $this->assertTrue(Notification::query()->where('user_id', $student->id)->where('type', 'knowledge_writing_entry_received')->exists());
        $second = $this->guide($student, 'Another guide');
        $this->actingAs($student)->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $second->id])->assertSessionHasErrors('publication');

        // Guides written before the challenge started don't count.
        $old = $this->guide($other, 'Old guide');
        KnowledgePublication::withoutGlobalScopes()->whereKey($old->id)->update(['published_at' => now()->subDays(3)]);
        $this->actingAs($other)->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $old->id])->assertSessionHasErrors('publication');
    }

    public function test_judging_results_payouts_and_winner_badge(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $challenge = $this->createChallenge($admin);
        $writers = User::factory()->count(3)->create();
        $entries = [];
        foreach ($writers as $i => $writer) {
            $guide = $this->guide($writer, 'Guide '.$i);
            $this->actingAs($writer)->post(route('knowledge.writing.enter', $challenge), ['publication_id' => $guide->id])->assertSessionHasNoErrors();
            $entries[] = KnowledgeWritingEntry::query()->where('user_id', $writer->id)->firstOrFail();
        }

        // Results can't be published while entries are still open.
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.writing.publish', $challenge))->assertSessionHasErrors();
        $challenge->update(['submission_deadline' => now()->subMinute()]);
        $this->assertSame('judging', $challenge->fresh()->phase());

        $scores = [[25, 25, 25, 25], [20, 20, 20, 20], [10, 10, 10, 10]];
        foreach ($entries as $i => $entry) {
            $this->post(route('admin.knowledge.read-earn.writing.score', $entry), ['scores' => array_combine(array_keys(KnowledgeWritingChallenge::CRITERIA), $scores[$i])])->assertSessionHasNoErrors();
        }
        $this->actingAs($writers[0])->post(route('admin.knowledge.read-earn.writing.score', $entries[0]), ['scores' => ['accuracy' => 1]])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.writing.publish', $challenge))->assertSessionHasNoErrors();
        $this->assertSame([1, 2, 3], KnowledgeWritingEntry::query()->orderBy('rank')->pluck('rank')->all());
        $this->assertSame($writers[0]->id, KnowledgeWritingEntry::query()->where('rank', 1)->value('user_id'));
        $drafts = KnowledgeRewardPayout::query()->where('scope_key', $challenge->scopeKey())->orderBy('rank')->get();
        $this->assertSame([5000000, 3000000, 2000000], $drafts->pluck('amount_minor')->map(fn ($v) => (int) $v)->all());
        $this->assertTrue(Notification::query()->where('user_id', $writers[2]->id)->where('type', 'knowledge_writing_results')->exists());

        $this->actingAs($writers[1])->get(route('knowledge.show', $entries[0]->publication))->assertOk()->assertSee('🏆 '.KnowledgeWritingEntry::query()->where('rank', 1)->value('award'));
        $this->get(route('knowledge.writing.show', $challenge))->assertOk()->assertSee('Guide 0');
    }

    public function test_announcement_reaches_active_users_once(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $challenge = $this->createChallenge($admin);
        User::factory()->count(3)->create();
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.writing.announce', $challenge))->assertSessionHasNoErrors();
        Queue::assertPushed(\App\Jobs\SendKnowledgeAnnouncementBatch::class);
        $this->assertNotNull($challenge->fresh()->announced_at);
        $this->post(route('admin.knowledge.read-earn.writing.announce', $challenge))->assertSessionHasErrors();
    }
}
