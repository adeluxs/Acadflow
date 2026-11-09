<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Jobs\SendKnowledgeAnnouncementBatch;
use App\Models\AuditLog;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeReadAttempt;
use App\Models\KnowledgeRewardPayout;
use App\Models\KnowledgeWritingChallenge;
use App\Models\KnowledgeWritingEntry;
use App\Models\KnowledgeWritingScore;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Official AcadSyntra Writing Challenges. Writers publish a guide or story on
 * the Knowledge Hub and enter it; readers' verified reads (passed quizzes)
 * form the live "Readers' favourite" board; platform judges score each entry;
 * the final score blends both. Prizes are platform-paid reward payouts.
 */
class WritingChallengeService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * The signed-in writer's own publications with a pass/fail checklist for this challenge.
     *
     * @return Collection<int,array{publication:KnowledgePublication,checks:array<string,bool>,eligible:bool,words:int,questions:int}>
     */
    public function eligibility(User $user, KnowledgeWritingChallenge $challenge): Collection
    {
        $entered = KnowledgeWritingEntry::query()->where('knowledge_writing_challenge_id', $challenge->id)->pluck('knowledge_publication_id')->all();

        return KnowledgePublication::withoutGlobalScopes()->where('creator_id', $user->id)
            ->whereIn('status', ['published', 'draft', 'submitted', 'pending_review', 'changes_requested'])
            ->with('document:id,word_count')->withCount(['quizQuestions as active_questions' => fn ($q) => $q->where('is_active', true)->whereNull('retired_at')])
            ->latest('updated_at')->limit(30)->get()
            ->map(function (KnowledgePublication $publication) use ($challenge, $entered): array {
                $words = (int) ($publication->document?->word_count ?? 0);
                $checks = [
                    'published' => $publication->status === 'published' && in_array($publication->visibility, ['public'], true),
                    'new' => $publication->published_at !== null && $publication->published_at->gte($challenge->starts_at),
                    'length' => $words >= $challenge->min_words,
                    'questions' => (int) $publication->active_questions >= $challenge->min_questions,
                    'track' => empty($challenge->tracks) || in_array((int) $publication->category_id, array_map('intval', (array) $challenge->tracks), true),
                    'not_entered' => ! in_array($publication->id, $entered, true),
                ];

                return ['publication' => $publication, 'checks' => $checks, 'eligible' => ! in_array(false, $checks, true), 'words' => $words, 'questions' => (int) $publication->active_questions];
            });
    }

    public function enter(User $user, KnowledgeWritingChallenge $challenge, int $publicationId): KnowledgeWritingEntry
    {
        if ($challenge->phase() !== 'open') {
            throw ValidationException::withMessages(['publication' => 'Entries are not open for this challenge.']);
        }
        $row = $this->eligibility($user, $challenge)->first(fn (array $row) => $row['publication']->id === $publicationId);
        if (! $row) {
            throw ValidationException::withMessages(['publication' => 'Choose a guide or story you wrote yourself.']);
        }
        if (! $row['eligible']) {
            throw ValidationException::withMessages(['publication' => 'That publication does not meet every entry rule yet. Check the list on this page.']);
        }

        $entry = DB::transaction(function () use ($user, $challenge, $row): KnowledgeWritingEntry {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (KnowledgeWritingEntry::query()->where('knowledge_writing_challenge_id', $challenge->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['publication' => 'You have already entered this challenge. Withdraw your entry first to enter a different piece.']);
            }

            return KnowledgeWritingEntry::create([
                'knowledge_writing_challenge_id' => $challenge->id, 'user_id' => $user->id,
                'knowledge_publication_id' => $row['publication']->id, 'track_category_id' => $row['publication']->category_id, 'status' => 'entered',
            ]);
        });
        $this->notifications->send($user, 'knowledge_writing_entry_received', 'You\'re in: '.$challenge->title,
            '"'.$row['publication']->title.'" is entered. Share it: every reader who passes its quiz pushes you up the Readers\' favourite board.',
            ['url' => route('knowledge.writing.show', $challenge), 'event_key' => 'writing-entry:'.$entry->id]);

        return $entry;
    }

    public function withdraw(User $user, KnowledgeWritingEntry $entry): void
    {
        abort_unless($entry->user_id === $user->id, 403);
        if ($entry->challenge->phase() !== 'open') {
            throw ValidationException::withMessages(['publication' => 'Entries can only be withdrawn while the challenge is open.']);
        }
        $entry->delete();
    }

    /** Live "Readers' favourite" board: verified reads since the challenge opened. */
    public function refreshReaderScores(KnowledgeWritingChallenge $challenge): void
    {
        $entries = $challenge->entries()->where('status', '!=', 'disqualified')->get(['id', 'knowledge_publication_id']);
        if ($entries->isEmpty()) {
            return;
        }
        $until = $challenge->results_published_at ?? min(now(), $challenge->results_at);
        $reads = KnowledgeReadAttempt::query()->whereIn('knowledge_publication_id', $entries->pluck('knowledge_publication_id'))
            ->where('status', KnowledgeReadAttempt::STATUS_COUNTED)->where('passed', true)
            ->whereBetween('created_at', [$challenge->starts_at, $until])
            ->groupBy('knowledge_publication_id')->selectRaw('knowledge_publication_id, COUNT(*) AS verified')->pluck('verified', 'knowledge_publication_id');
        $max = max(1, (int) $reads->max());
        foreach ($entries as $entry) {
            $count = (int) ($reads[$entry->knowledge_publication_id] ?? 0);
            KnowledgeWritingEntry::query()->whereKey($entry->id)->update(['verified_reads' => $count, 'reader_score' => round($count * 100 / $max, 2)]);
        }
    }

    /** @return Collection<int,KnowledgeWritingEntry> */
    public function readersBoard(KnowledgeWritingChallenge $challenge, int $limit = 10): Collection
    {
        return $challenge->entries()->where('status', '!=', 'disqualified')->with(['publication:id,uuid,slug,title,category_id', 'user:id,first_name,last_name,university_id', 'user.university:id,short_name'])
            ->orderByDesc('verified_reads')->orderBy('created_at')->limit($limit)->get();
    }

    /** @param array<string,int|string> $scores criterion => 0..25 */
    public function score(KnowledgeWritingEntry $entry, User $judge, array $scores, ?string $comment): void
    {
        abort_unless($judge->isSuperAdmin(), 403);
        if ($entry->challenge->results_published_at) {
            throw ValidationException::withMessages(['scores' => 'Results are already published.']);
        }
        $clean = [];
        foreach (array_keys(KnowledgeWritingChallenge::CRITERIA) as $key) {
            $value = $scores[$key] ?? null;
            if (! is_numeric($value) || $value < 0 || $value > 25) {
                throw ValidationException::withMessages(['scores' => 'Give every criterion a score from 0 to 25.']);
            }
            $clean[$key] = (int) $value;
        }
        KnowledgeWritingScore::updateOrCreate(['knowledge_writing_entry_id' => $entry->id, 'judge_id' => $judge->id],
            ['scores' => $clean, 'total' => array_sum($clean), 'comment' => $comment ? mb_substr($comment, 0, 1000) : null]);
        $entry->update(['judge_score' => round((float) $entry->scores()->avg('total'), 2), 'status' => $entry->status === 'disqualified' ? 'disqualified' : 'judged']);
    }

    public function setDisqualified(KnowledgeWritingEntry $entry, bool $disqualified, ?string $note, User $admin): void
    {
        abort_unless($admin->isSuperAdmin(), 403);
        $entry->update(['status' => $disqualified ? 'disqualified' : ($entry->judge_score !== null ? 'judged' : 'entered'), 'note' => $note, 'rank' => null, 'award' => null]);
        AuditLog::log('knowledge.writing_entry.'.($disqualified ? 'disqualified' : 'reinstated'), $admin->id, KnowledgeWritingEntry::class, $entry->id, null, ['note' => $note]);
    }

    /** Blends judges and readers, ranks, marks finalists and awards. Returns the ranked entries. */
    public function computeResults(KnowledgeWritingChallenge $challenge): Collection
    {
        $this->refreshReaderScores($challenge);
        $weight = max(0, min(100, $challenge->judge_weight)) / 100;
        $entries = $challenge->entries()->with('track:id,name')->get();
        $scored = $entries->filter(fn ($e) => $e->status !== 'disqualified' && $e->judge_score !== null)
            ->each(fn ($e) => $e->final_score = round($e->judge_score * $weight + (float) $e->reader_score * (1 - $weight), 2))
            ->sortBy([['final_score', 'desc'], ['verified_reads', 'desc'], ['created_at', 'asc']])->values();

        $places = ['1st place', '2nd place', '3rd place'];
        $prizeCount = count((array) $challenge->prizes);
        $winners = [];
        foreach ($entries as $entry) {
            $entry->rank = null;
            $entry->award = null;
            if ($entry->status !== 'disqualified') {
                $entry->status = $entry->judge_score !== null ? 'judged' : 'entered';
            }
        }
        foreach ($scored as $index => $entry) {
            $entry->rank = $index + 1;
            if ($index < $challenge->finalists) {
                $entry->status = 'finalist';
            }
            if ($index < $prizeCount) {
                $entry->award = $places[$index] ?? ($index + 1).'th place';
                $entry->status = 'winner';
                $winners[] = $entry->user_id;
            }
        }
        if ($challenge->track_prize_minor > 0) {
            foreach ((array) $challenge->tracks as $trackId) {
                $best = $scored->first(fn ($e) => (int) $e->track_category_id === (int) $trackId && ! in_array($e->user_id, $winners, true));
                if ($best) {
                    $best->award = 'Best in '.($best->track?->name ?? 'track');
                    $best->status = 'winner';
                    $winners[] = $best->user_id;
                }
            }
        }
        foreach ($entries as $entry) {
            $entry->save();
        }

        return $scored;
    }

    public function publishResults(KnowledgeWritingChallenge $challenge, User $admin): int
    {
        abort_unless($admin->isSuperAdmin(), 403);
        if ($challenge->phase() !== 'judging') {
            throw ValidationException::withMessages(['challenge' => 'Results can be published after the entry deadline and only once.']);
        }
        if ($challenge->entries()->where('status', '!=', 'disqualified')->whereNull('judge_score')->exists()) {
            throw ValidationException::withMessages(['challenge' => 'Score every entry (or disqualify it) before publishing results.']);
        }

        return DB::transaction(function () use ($challenge, $admin): int {
            $ranked = $this->computeResults($challenge);
            $challenge->update(['results_published_at' => now()]);
            $prizes = array_values((array) $challenge->prizes);
            $drafts = 0;
            foreach ($challenge->entries()->whereNotNull('award')->get() as $entry) {
                $amount = $entry->rank !== null && $entry->rank <= count($prizes) && ! str_starts_with((string) $entry->award, 'Best in') ? (int) $prizes[$entry->rank - 1] : (int) $challenge->track_prize_minor;
                if ($amount <= 0) {
                    continue;
                }
                KnowledgeRewardPayout::updateOrCreate(['scope_key' => $challenge->scopeKey(), 'user_id' => $entry->user_id], [
                    'kind' => 'writing_challenge', 'rank' => $entry->rank, 'points' => (int) round((float) $entry->final_score),
                    'amount_minor' => $amount, 'status' => 'draft', 'metadata' => ['award' => $entry->award, 'entry_uuid' => $entry->uuid, 'publication_id' => $entry->knowledge_publication_id],
                ]);
                $drafts++;
            }
            AuditLog::log('knowledge.writing_challenge.results_published', $admin->id, KnowledgeWritingChallenge::class, $challenge->id, null, ['ranked' => $ranked->count(), 'prize_drafts' => $drafts]);

            DB::afterCommit(function () use ($challenge): void {
                $challenge->entries()->with('user')->get()->each(function (KnowledgeWritingEntry $entry) use ($challenge): void {
                    if (! $entry->user) {
                        return;
                    }
                    [$title, $message] = match (true) {
                        $entry->award !== null => ['🏆 You won: '.$entry->award, 'Congratulations! Your entry won '.$entry->award.' in '.$challenge->title.'. Your prize will be paid into your withdrawable earnings shortly.'],
                        $entry->status === 'finalist' => ['You\'re a finalist in '.$challenge->title, 'Your entry finished #'.$entry->rank.' and earned a Finalist badge on the Knowledge Hub.'],
                        default => ['Results are out: '.$challenge->title, 'Thank you for entering. See the winners and the Readers\' favourite board.'],
                    };
                    $this->notifications->send($entry->user, 'knowledge_writing_results', $title, $message, ['url' => route('knowledge.writing.show', $challenge), 'event_key' => 'writing-results:'.$entry->id]);
                });
            });

            return $drafts;
        }, 3);
    }

    /** Queues an "official challenge is live" announcement to every active user (once). */
    public function announce(KnowledgeWritingChallenge $challenge, User $admin): int
    {
        abort_unless($admin->isSuperAdmin(), 403);
        if (! in_array($challenge->phase(), ['upcoming', 'open'], true) || $challenge->announced_at) {
            throw ValidationException::withMessages(['challenge' => 'Announce a published challenge once, before entries close.']);
        }
        $challenge->update(['announced_at' => now()]);
        $title = '✍️ New AcadSyntra Writing Challenge: ₦'.number_format(intdiv($challenge->prizePoolMinor(), 100)).' in prizes';
        $message = ($challenge->theme ? 'Theme: '.$challenge->theme.'. ' : '').'Write a guide or story on the Knowledge Hub and enter by '.$challenge->submission_deadline->format('j M').'.';
        $batches = 0;
        User::withoutGlobalScopes()->where('is_active', true)->select('id')->chunkById(500, function ($users) use ($challenge, $title, $message, &$batches): void {
            SendKnowledgeAnnouncementBatch::dispatch($users->pluck('id')->all(), 'knowledge_challenge_announcement', $title, $message, route('knowledge.writing.show', $challenge), 'writing-announce:'.$challenge->id);
            $batches++;
        });

        return $batches;
    }

    /** @return Collection<int,KnowledgeCategory> */
    public function trackCategories(KnowledgeWritingChallenge $challenge): Collection
    {
        return empty($challenge->tracks) ? collect() : KnowledgeCategory::query()->whereIn('id', (array) $challenge->tracks)->orderBy('sort_order')->get(['id', 'name']);
    }
}
