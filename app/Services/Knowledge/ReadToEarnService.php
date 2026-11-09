<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\AuditLog;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizDraw;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeReadAttempt;
use App\Models\KnowledgeReaderView;
use App\Models\User;
use App\Services\Commerce\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quiz-verified reading. Points come only from passing questions about the
 * publication actually read; anti-farming checks decide whether a pass counts.
 */
class ReadToEarnService
{
    /** Most questions a guide's bank can hold (AI and creator questions together). */
    public const MAX_QUESTIONS = 30;

    public const REASONS = [
        'below_pass_mark' => 'Score was below the pass mark.',
        'no_reader_pass' => 'Earning needs an active Reader Pass.',
        'email_not_verified' => 'Verify your email address to earn.',
        'too_fast' => 'Submitted faster than the guide can be read.',
        'daily_limit' => 'Daily earning limit reached.',
        'creator_daily_limit' => 'Daily limit for this creator reached.',
        'shared_network' => 'Held for review: several accounts used the same network today.',
        'module_disabled' => 'Read & Earn is switched off.',
        'admin_rejected' => 'Rejected after review.',
        'awaiting_pass' => 'Points saved. Get a Reader Pass to collect them.',
        'pass_too_late' => 'Saved points expired before a Reader Pass was activated.',
    ];

    public function __construct(
        private readonly ReadEarnSettings $settings,
        private readonly ReaderPassService $passes,
        private readonly WalletService $wallets,
    ) {}

    /** @return Collection<int,KnowledgeQuizQuestion> */
    public function questions(KnowledgePublication $publication): Collection
    {
        return $publication->quizQuestions()->where('is_active', true)->whereNull('retired_at')->limit(self::MAX_QUESTIONS)->get();
    }

    public function perReader(): int
    {
        return max(1, min(15, $this->settings->int('knowledge_quiz_per_reader')));
    }

    /**
     * This reader's own quiz: a random set from the bank with the options shuffled, saved so a
     * refresh shows the same quiz (no fishing for easier questions) and scoring uses exactly
     * what they were shown. A new draw is made only if a question they had was removed.
     * Each returned question carries display_options (shown order) and display_order (original indexes).
     *
     * @return Collection<int,KnowledgeQuizQuestion>
     */
    public function drawFor(User $user, KnowledgePublication $publication, ?Collection $bank = null): Collection
    {
        $bank = ($bank ?? $this->questions($publication))->keyBy('id');
        if ($bank->isEmpty()) {
            return collect();
        }
        $draw = KnowledgeQuizDraw::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->first();
        $items = $draw?->questions ?? [];
        $valid = $items !== [] && collect($items)->every(fn ($item) => $bank->has((int) ($item['id'] ?? 0))
            && $this->isPermutation((array) ($item['order'] ?? []), count((array) $bank[(int) $item['id']]->options)));
        if (! $valid) {
            $items = $bank->keys()->shuffle()->take(min($this->perReader(), $bank->count()))->map(function ($id) use ($bank) {
                return ['id' => (int) $id, 'order' => collect(array_keys((array) $bank[$id]->options))->shuffle()->values()->all()];
            })->values()->all();
            $draw = KnowledgeQuizDraw::query()->updateOrCreate(['user_id' => $user->id, 'knowledge_publication_id' => $publication->id], ['questions' => $items]);
        }

        return collect($items)->map(function ($item) use ($bank) {
            $question = clone $bank[(int) $item['id']];
            $question->display_order = array_map('intval', $item['order']);
            $question->display_options = array_map(fn ($original) => $question->options[$original], $question->display_order);

            return $question;
        })->values();
    }

    /** The questions a finished attempt was marked on, in the order answered. @return Collection<int,KnowledgeQuizQuestion> */
    public function attemptQuestions(KnowledgeReadAttempt $attempt): Collection
    {
        $ids = array_map('intval', array_keys((array) $attempt->answers));
        if ($ids === []) {
            return collect();
        }
        $found = KnowledgeQuizQuestion::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->filter(fn ($id) => $found->has($id))->map(fn ($id) => $found[$id])->values();
    }

    /**
     * A reader reports a wrong or unclear question. Only readers who were given it can report
     * it, once each. At the configured number of reports it stops being asked.
     */
    public function report(User $user, KnowledgeQuizQuestion $question, ?string $reason): bool
    {
        $served = KnowledgeQuizDraw::query()->where('user_id', $user->id)->where('knowledge_publication_id', $question->knowledge_publication_id)->first()?->questions ?? [];
        $answered = (array) (KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('knowledge_publication_id', $question->knowledge_publication_id)->value('answers') ?? []);
        if (is_string($answered)) {
            $answered = (array) json_decode($answered, true);
        }
        abort_unless(collect($served)->contains(fn ($item) => (int) ($item['id'] ?? 0) === $question->id) || array_key_exists($question->id, $answered) || array_key_exists((string) $question->id, $answered), 403);

        return DB::transaction(function () use ($user, $question, $reason): bool {
            $inserted = DB::table('knowledge_quiz_reports')->insertOrIgnore([
                'knowledge_quiz_question_id' => $question->id, 'user_id' => $user->id,
                'reason' => $reason !== null ? mb_substr(trim($reason), 0, 300) : null, 'created_at' => now(),
            ]);
            if ($inserted < 1) {
                return false;
            }
            DB::table('knowledge_quiz_questions')->where('id', $question->id)->increment('report_count');
            $count = (int) DB::table('knowledge_quiz_questions')->where('id', $question->id)->value('report_count');
            if ($count >= max(1, $this->settings->int('knowledge_quiz_report_limit')) && $question->retired_at === null) {
                DB::table('knowledge_quiz_questions')->where('id', $question->id)->update(['retired_at' => now(), 'updated_at' => now()]);
            }

            return true;
        });
    }

    /** @param array<int,mixed> $order */
    private function isPermutation(array $order, int $size): bool
    {
        $values = array_map('intval', $order);
        sort($values);

        return $size > 0 && $values === range(0, $size - 1);
    }

    public function isEarnable(KnowledgePublication $publication, ?Collection $questions = null): bool
    {
        $count = $questions?->count() ?? $publication->quizQuestions()->where('is_active', true)->count();

        return $publication->status === 'published' && $count >= max(1, $this->settings->int('knowledge_read_earn_min_questions'));
    }

    public function attemptFor(User $user, KnowledgePublication $publication): ?KnowledgeReadAttempt
    {
        return KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->first();
    }

    /** @param array<int|string,int|string> $answers question id => chosen option index */
    public function submit(User $user, KnowledgePublication $publication, array $answers, ?string $reflection, ?string $ip): KnowledgeReadAttempt
    {
        abort_unless($publication->status === 'published', 404);
        if ($publication->creator_id === $user->id) {
            throw ValidationException::withMessages(['answers' => 'Creators cannot earn from their own publications.']);
        }
        $bank = $this->questions($publication);
        if (! $this->isEarnable($publication, $bank)) {
            throw ValidationException::withMessages(['answers' => 'This publication is not set up for Read & Earn yet.']);
        }

        return DB::transaction(function () use ($user, $publication, $answers, $reflection, $ip, $bank): KnowledgeReadAttempt {
            // Serialise one reader's submissions so daily caps cannot be raced.
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($this->attemptFor($user, $publication)) {
                throw ValidationException::withMessages(['answers' => 'You have already taken the quiz for this publication.']);
            }

            // Mark exactly the questions this reader was shown, mapping each shown option back to the original.
            $draw = KnowledgeQuizDraw::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->first();
            $questions = $this->drawFor($user, $publication, $bank);
            // No saved draw means the quiz was never shown; a replaced draw means a question was removed meanwhile.
            if (! $draw || collect($draw->questions)->pluck('id')->map(fn ($id) => (int) $id)->all() !== $questions->pluck('id')->map(fn ($id) => (int) $id)->all()) {
                throw ValidationException::withMessages(['answers' => 'This quiz changed since you opened it. Please answer the questions shown now.']);
            }
            $chosen = [];
            $correct = 0;
            foreach ($questions as $question) {
                $pick = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
                if ($pick === null || ! is_numeric($pick) || ! array_key_exists((int) $pick, $question->display_order)) {
                    throw ValidationException::withMessages(['answers' => 'Answer every question before submitting.']);
                }
                $original = $question->display_order[(int) $pick];
                $chosen[$question->id] = $original;
                $correct += $original === $question->correct_option ? 1 : 0;
            }
            $total = $questions->count();
            $score = (int) round($correct * 100 / max(1, $total));
            $passed = $score >= $this->settings->int('knowledge_read_earn_pass_mark');

            $reflection = trim((string) $reflection);
            $words = $reflection === '' ? 0 : str_word_count(strip_tags($reflection));
            $period = now()->format('Y-m');
            $readSeconds = $this->readSeconds($user, $publication, $period);
            $hadPass = $this->passes->hasPass($user);
            $ipHash = $ip ? hash('sha256', $ip.'|'.config('app.key')) : null;

            $reason = $this->blockingReason($user, $publication, $passed, $hadPass, $readSeconds);
            $status = $reason === null ? KnowledgeReadAttempt::STATUS_COUNTED : KnowledgeReadAttempt::STATUS_NOT_COUNTED;
            // No pass yet but everything else is fine: save the points so the reader can collect them with a pass.
            if ($reason === 'no_reader_pass' && $this->settings->int('knowledge_read_earn_hold_days') > 0
                && $this->blockingReason($user, $publication, $passed, true, $readSeconds) === null) {
                [$status, $reason] = [KnowledgeReadAttempt::STATUS_HELD, 'awaiting_pass'];
            }
            if (in_array($status, [KnowledgeReadAttempt::STATUS_COUNTED, KnowledgeReadAttempt::STATUS_HELD], true) && $ipHash && $this->sharedNetwork($user, $ipHash)) {
                [$status, $reason] = [KnowledgeReadAttempt::STATUS_FLAGGED, 'shared_network'];
            }

            $points = $status === KnowledgeReadAttempt::STATUS_NOT_COUNTED ? 0 : $this->points($score, $words);
            $attempt = KnowledgeReadAttempt::create([
                'user_id' => $user->id,
                'knowledge_publication_id' => $publication->id,
                'creator_id' => $publication->creator_id,
                'period' => $period,
                'question_count' => $total,
                'correct_count' => $correct,
                'score_percent' => $score,
                'passed' => $passed,
                'reflection' => $reflection === '' ? null : mb_substr($reflection, 0, 3000),
                'reflection_words' => min(65535, $words),
                'points' => $points,
                'had_pass' => $hadPass,
                'counts_for_creator' => false,
                'status' => $status,
                'reason' => $reason,
                'read_seconds' => $readSeconds,
                'ip_hash' => $ipHash,
                'answers' => $chosen,
            ]);

            if ($status === KnowledgeReadAttempt::STATUS_COUNTED) {
                $this->award($attempt, $user);
            }

            return $attempt->fresh();
        }, 3);
    }

    /**
     * Pays the saved points of a reader who now has a Reader Pass. Points saved longer ago
     * than the hold window expire. Safe to call on every page view: it does nothing when
     * there is nothing waiting.
     */
    public function releaseHeld(User $user): int
    {
        if (! KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('status', KnowledgeReadAttempt::STATUS_HELD)->exists() || ! $this->passes->hasPass($user)) {
            return 0;
        }
        $cutoff = now()->subDays(max(0, $this->settings->int('knowledge_read_earn_hold_days')));

        return DB::transaction(function () use ($user, $cutoff): int {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $released = 0;
            $held = KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('status', KnowledgeReadAttempt::STATUS_HELD)->orderBy('id')->lockForUpdate()->get();
            foreach ($held as $attempt) {
                if ($attempt->created_at->lt($cutoff)) {
                    $attempt->forceFill(['status' => KnowledgeReadAttempt::STATUS_NOT_COUNTED, 'reason' => 'pass_too_late', 'points' => 0])->save();

                    continue;
                }
                $attempt->forceFill(['status' => KnowledgeReadAttempt::STATUS_COUNTED, 'reason' => null, 'had_pass' => true])->save();
                $this->award($attempt, $user);
                $released++;
            }

            return $released;
        }, 3);
    }

    /** Points saved for this reader, waiting for a Reader Pass. */
    public function heldPoints(User $user): int
    {
        $cutoff = now()->subDays(max(0, $this->settings->int('knowledge_read_earn_hold_days')));

        return (int) KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('status', KnowledgeReadAttempt::STATUS_HELD)->where('created_at', '>=', $cutoff)->sum('points');
    }

    /** Admin decision on an attempt held for review. */
    public function review(KnowledgeReadAttempt $attempt, User $admin, bool $approve): KnowledgeReadAttempt
    {
        abort_unless($admin->isSuperAdmin(), 403);

        return DB::transaction(function () use ($attempt, $admin, $approve): KnowledgeReadAttempt {
            $locked = KnowledgeReadAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === KnowledgeReadAttempt::STATUS_FLAGGED, 422, 'Only attempts held for review can be decided.');
            $reader = User::query()->findOrFail($locked->user_id);
            if ($approve && $this->settings->bool('knowledge_read_earn_requires_pass') && ! $locked->had_pass && ! $this->passes->hasPass($reader)) {
                // Approved, but the reader still has no pass: keep the points saved for them.
                $locked->forceFill(['status' => KnowledgeReadAttempt::STATUS_HELD, 'reason' => 'awaiting_pass', 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            } elseif ($approve) {
                $locked->forceFill(['status' => KnowledgeReadAttempt::STATUS_COUNTED, 'reason' => null, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
                $this->award($locked, $reader);
            } else {
                $locked->forceFill(['status' => KnowledgeReadAttempt::STATUS_REJECTED, 'reason' => 'admin_rejected', 'points' => 0, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            }
            AuditLog::log('knowledge.read_attempt.'.($approve ? 'approved' : 'rejected'), $admin->id, KnowledgeReadAttempt::class, $locked->id, 'flagged', $locked->status);

            return $locked->fresh();
        }, 3);
    }

    /** @return array<string,mixed> */
    public function summary(User $user): array
    {
        $period = now()->format('Y-m');
        $month = KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('period', $period)
            ->selectRaw("COUNT(*) AS attempts, SUM(CASE WHEN status = 'counted' THEN 1 ELSE 0 END) AS counted, SUM(CASE WHEN status = 'counted' THEN points ELSE 0 END) AS points, SUM(credit_minor) AS credit_minor, AVG(CASE WHEN status = 'counted' THEN score_percent END) AS average")
            ->first();
        $lifetime = KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('status', KnowledgeReadAttempt::STATUS_COUNTED)
            ->selectRaw('COUNT(*) AS read_count, COUNT(DISTINCT creator_id) AS creator_count')->first();

        return [
            'period' => $period,
            'attempts' => (int) ($month->attempts ?? 0),
            'counted' => (int) ($month->counted ?? 0),
            'points' => (int) ($month->points ?? 0),
            'credit_minor' => (int) ($month->credit_minor ?? 0),
            'average' => (int) round((float) ($month->average ?? 0)),
            'lifetime_reads' => (int) ($lifetime->read_count ?? 0),
            'lifetime_creators' => (int) ($lifetime->creator_count ?? 0),
            'account_days' => (int) $user->created_at?->diffInDays(now()),
            'held_points' => $this->heldPoints($user),
        ];
    }

    private function readSeconds(User $user, KnowledgePublication $publication, string $period): int
    {
        $opened = KnowledgeReaderView::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->where('period', $period)->value('first_opened_at');

        return $opened ? max(0, (int) abs(now()->diffInSeconds($opened))) : 0;
    }

    public function minimumSeconds(KnowledgePublication $publication): int
    {
        $publication->loadMissing('document');
        $words = str_word_count(strip_tags((string) $publication->document?->body));
        $wpm = max(60, $this->settings->int('knowledge_read_earn_max_wpm'));

        return max($this->settings->int('knowledge_read_earn_min_seconds'), (int) ceil($words / $wpm * 60));
    }

    private function blockingReason(User $user, KnowledgePublication $publication, bool $passed, bool $hadPass, int $readSeconds): ?string
    {
        if (! $this->settings->moduleEnabled($user->university_id)) {
            return 'module_disabled';
        }
        if (! $passed) {
            return 'below_pass_mark';
        }
        if ($this->settings->bool('knowledge_read_earn_requires_pass') && ! $hadPass) {
            return 'no_reader_pass';
        }
        if (! $user->email_verified_at) {
            return 'email_not_verified';
        }
        if ($readSeconds < $this->minimumSeconds($publication)) {
            return 'too_fast';
        }
        $today = KnowledgeReadAttempt::query()->where('user_id', $user->id)->whereIn('status', [KnowledgeReadAttempt::STATUS_COUNTED, KnowledgeReadAttempt::STATUS_FLAGGED, KnowledgeReadAttempt::STATUS_HELD])->where('created_at', '>=', now()->startOfDay());
        if ((clone $today)->count() >= $this->settings->int('knowledge_read_earn_daily_cap')) {
            return 'daily_limit';
        }
        if ($publication->creator_id && (clone $today)->where('creator_id', $publication->creator_id)->count() >= $this->settings->int('knowledge_read_earn_creator_daily_cap')) {
            return 'creator_daily_limit';
        }

        return null;
    }

    private function sharedNetwork(User $user, string $ipHash): bool
    {
        $others = KnowledgeReadAttempt::query()->where('ip_hash', $ipHash)->where('created_at', '>=', now()->startOfDay())
            ->where('user_id', '!=', $user->id)->distinct()->count('user_id');

        return $others >= max(1, $this->settings->int('knowledge_read_earn_max_accounts_per_ip'));
    }

    private function points(int $score, int $reflectionWords): int
    {
        $points = (int) round($this->settings->int('knowledge_read_earn_points_per_read') * $score / 100);
        $bonus = $this->settings->int('knowledge_read_earn_reflection_bonus');
        if ($bonus > 0 && $reflectionWords >= max(1, $this->settings->int('knowledge_read_earn_reflection_min_words'))) {
            $points += $bonus;
        }

        return $points;
    }

    /** Pays the instant study credit and decides whether the read counts for the creator. */
    private function award(KnowledgeReadAttempt $attempt, User $user): void
    {
        $minReaderDays = $this->settings->int('knowledge_creator_pool_min_reader_days');
        $countsForCreator = $attempt->creator_id !== null && $user->created_at !== null && $user->created_at->lte(now()->subDays($minReaderDays));

        $perPoint = $this->settings->minor('knowledge_read_earn_credit_per_point');
        $credit = 0;
        if ($perPoint > 0 && $attempt->points > 0) {
            $earned = (int) KnowledgeReadAttempt::query()->where('user_id', $user->id)->where('period', $attempt->period)->sum('credit_minor');
            $credit = max(0, min($attempt->points * $perPoint, $this->settings->minor('knowledge_read_earn_monthly_credit_cap') - $earned));
        }
        if ($credit > 0) {
            $this->wallets->creditSpending($user, $credit, 'knowledge_reward_credit', $attempt, null, 'Read & Earn study credit', ['publication_id' => $attempt->knowledge_publication_id]);
        }
        $attempt->forceFill(['credit_minor' => $credit, 'counts_for_creator' => $countsForCreator])->save();
    }
}
