<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\AcademicChallenge;
use App\Models\AuditLog;
use App\Models\CreatorMonetizationApplication;
use App\Models\KnowledgeFollow;
use App\Models\KnowledgeReadAttempt;
use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeRewardPayout;
use App\Models\User;
use App\Services\Commerce\WalletService;
use App\Services\SocialNotificationService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns verified reading into fixed-budget cash: monthly reader and creator
 * pools, and challenge prizes. Every payout is a draft first; a super admin
 * reviews, can exclude anyone, then pays. The cost can never exceed the budget.
 */
class RewardPoolService
{
    public function __construct(
        private readonly ReadEarnSettings $settings,
        private readonly WalletService $wallets,
        private readonly SocialNotificationService $notifications,
    ) {}

    public static function poolScope(string $kind, string $period): string
    {
        return $kind.':'.$period;
    }

    public function assertClosedPeriod(string $period): Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => 'Choose a month in the format YYYY-MM.']);
        }
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        if ($start->copy()->endOfMonth()->isFuture()) {
            throw ValidationException::withMessages(['period' => 'A pool can only be calculated after the month has ended.']);
        }

        return $start;
    }

    /** @return Collection<int,KnowledgeRewardPayout> */
    public function calculateReaderPool(string $period, User $admin): Collection
    {
        $start = $this->assertClosedPeriod($period);
        $end = $start->copy()->endOfMonth();
        $requiresPass = $this->settings->bool('knowledge_read_earn_requires_pass');

        $rows = KnowledgeReadAttempt::query()->where('period', $period)->where('status', KnowledgeReadAttempt::STATUS_COUNTED)
            ->when($requiresPass, fn ($q) => $q->where('had_pass', true))
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points) AS total_points, AVG(score_percent) AS average_score, COUNT(*) AS read_count')
            ->get()->keyBy('user_id');
        $ids = $rows->keys()->all();
        $lifetime = $ids === [] ? collect() : KnowledgeReadAttempt::query()->whereIn('user_id', $ids)->where('status', KnowledgeReadAttempt::STATUS_COUNTED)
            ->where('created_at', '<=', $end)->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) AS read_count, COUNT(DISTINCT creator_id) AS creator_count')->get()->keyBy('user_id');
        $users = $ids === [] ? collect() : User::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'created_at', 'email_verified_at', 'is_active'])->keyBy('id');

        $minDays = $this->settings->int('knowledge_reader_pool_min_account_days');
        $weights = [];
        $meta = [];
        foreach ($rows as $userId => $row) {
            $user = $users->get($userId);
            $life = $lifetime->get($userId);
            $eligible = $user && $user->is_active && $user->email_verified_at
                && $user->created_at && $user->created_at->lte($end->copy()->subDays($minDays))
                && (int) ($life->read_count ?? 0) >= $this->settings->int('knowledge_reader_pool_min_passed_reads')
                && (int) ($life->creator_count ?? 0) >= $this->settings->int('knowledge_reader_pool_min_creators')
                && (float) $row->average_score >= $this->settings->int('knowledge_reader_pool_min_average')
                && (int) $row->total_points > 0;
            if ($eligible) {
                $weights[(int) $userId] = (int) $row->total_points;
                $meta[(int) $userId] = ['reads' => (int) $row->read_count, 'average_score' => (int) round((float) $row->average_score)];
            }
        }

        return $this->writeDrafts('reader_pool', self::poolScope('reader_pool', $period), $weights, $meta,
            $this->settings->minor('knowledge_reader_pool_amount'), $this->settings->int('knowledge_reader_pool_max_share'), $admin);
    }

    /** @return Collection<int,KnowledgeRewardPayout> */
    public function calculateCreatorPool(string $period, User $admin): Collection
    {
        $this->assertClosedPeriod($period);
        $rows = KnowledgeReadAttempt::query()->where('period', $period)->where('status', KnowledgeReadAttempt::STATUS_COUNTED)
            ->where('counts_for_creator', true)->whereNotNull('creator_id')
            ->groupBy('creator_id')->selectRaw('creator_id, COUNT(*) AS verified_reads')
            ->havingRaw('COUNT(*) >= ?', [max(1, $this->settings->int('knowledge_creator_pool_min_verified_reads'))])
            ->get()->keyBy('creator_id');
        $ids = $rows->keys()->map(fn ($id) => (int) $id)->all();
        if ($ids !== [] && $this->settings->bool('knowledge_creator_pool_requires_approval')) {
            $approved = CreatorMonetizationApplication::query()->whereIn('user_id', $ids)->where('status', 'approved')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_intersect($ids, $approved));
        }
        $minFollowers = $this->settings->int('knowledge_creator_pool_min_followers');
        if ($ids !== [] && $minFollowers > 0) {
            $followers = KnowledgeFollow::query()->whereIn('target_type', KnowledgeNotificationService::CREATOR_TARGETS)->whereIn('target_id', $ids)
                ->groupBy('target_id')->selectRaw('target_id, COUNT(*) AS follower_count')->pluck('follower_count', 'target_id');
            $ids = array_values(array_filter($ids, fn (int $id) => (int) ($followers[$id] ?? 0) >= $minFollowers));
        }
        $active = $ids === [] ? [] : User::withoutGlobalScopes()->whereIn('id', $ids)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $weights = [];
        $meta = [];
        foreach ($active as $id) {
            $weights[$id] = (int) $rows[$id]->verified_reads;
            $meta[$id] = ['verified_reads' => (int) $rows[$id]->verified_reads];
        }

        return $this->writeDrafts('creator_pool', self::poolScope('creator_pool', $period), $weights, $meta,
            $this->settings->minor('knowledge_creator_pool_amount'), $this->settings->int('knowledge_creator_pool_max_share'), $admin);
    }

    /**
     * Reading Challenge ranking: points from counted quizzes inside the window.
     *
     * @return Collection<int,object{user_id:int,total_points:int,read_count:int,last_at:string}>
     */
    public function leaderboard(KnowledgeReadingChallenge $challenge, int $limit = 50): Collection
    {
        return KnowledgeReadAttempt::query()
            ->from('knowledge_read_attempts as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.status', KnowledgeReadAttempt::STATUS_COUNTED)
            ->whereBetween('a.created_at', [$challenge->starts_at, $challenge->ends_at])
            ->where('u.is_active', true)
            ->when($challenge->university_id, fn ($q) => $q->where('u.university_id', $challenge->university_id))
            ->groupBy('a.user_id')
            ->havingRaw('COUNT(*) >= ?', [max(1, (int) $challenge->min_passed_reads)])
            ->selectRaw('a.user_id, SUM(a.points) AS total_points, COUNT(*) AS read_count, MAX(a.created_at) AS last_at')
            ->orderByDesc('total_points')->orderBy('last_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int,KnowledgeRewardPayout> */
    public function calculateReadingChallenge(KnowledgeReadingChallenge $challenge, User $admin): Collection
    {
        if ($challenge->ends_at->isFuture()) {
            throw ValidationException::withMessages(['challenge' => 'Winners can be calculated after the challenge ends.']);
        }
        $excluded = $this->excludedUsers($challenge->scopeKey());
        $board = $this->leaderboard($challenge, 500)->reject(fn ($row) => in_array((int) $row->user_id, $excluded, true))->values();
        $ranked = [];
        foreach (array_values((array) $challenge->prizes) as $index => $amountMinor) {
            $row = $board->get($index);
            if (! $row || (int) $amountMinor <= 0) {
                continue;
            }
            $ranked[] = ['user_id' => (int) $row->user_id, 'rank' => $index + 1, 'amount' => (int) $amountMinor, 'points' => (int) $row->total_points, 'meta' => ['reads' => (int) $row->read_count]];
        }

        return $this->writeRanked('reading_challenge', $challenge->scopeKey(), $ranked, $admin);
    }

    public function setExcluded(KnowledgeRewardPayout $payout, bool $excluded, ?string $note, User $admin): void
    {
        abort_unless($admin->isSuperAdmin(), 403);
        DB::transaction(function () use ($payout, $excluded, $note, $admin): void {
            $locked = KnowledgeRewardPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'paid', 422, 'A paid reward cannot be changed.');
            $locked->update(['status' => $excluded ? 'excluded' : 'draft', 'note' => $note]);
            AuditLog::log('knowledge.reward.'.($excluded ? 'excluded' : 'included'), $admin->id, KnowledgeRewardPayout::class, $locked->id, null, ['note' => $note]);
        });
    }

    /** Pays every draft in a scope into the recipients' withdrawable earnings. */
    public function pay(string $scopeKey, User $admin): int
    {
        abort_unless($admin->isSuperAdmin(), 403);
        $paid = 0;
        $ids = KnowledgeRewardPayout::query()->where('scope_key', $scopeKey)->where('status', 'draft')->where('amount_minor', '>', 0)->pluck('id');
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id, $admin): ?KnowledgeRewardPayout {
                $payout = KnowledgeRewardPayout::query()->whereKey($id)->lockForUpdate()->first();
                if (! $payout || $payout->status !== 'draft') {
                    return null;
                }
                $user = User::withoutGlobalScopes()->find($payout->user_id);
                if (! $user || ! $user->is_active) {
                    $payout->update(['status' => 'excluded', 'note' => 'Account inactive at payment time.']);

                    return null;
                }
                $label = KnowledgeRewardPayout::KINDS[$payout->kind] ?? 'Knowledge Hub reward';
                $this->wallets->creditAvailableEarnings($user, (int) $payout->amount_minor, 'knowledge_reward_cash', $payout, $label, ['scope' => $payout->scope_key, 'paid_by' => $admin->id]);
                $payout->update(['status' => 'paid', 'paid_by' => $admin->id, 'paid_at' => now()]);
                AuditLog::log('knowledge.reward.paid', $admin->id, KnowledgeRewardPayout::class, $payout->id, 'draft', ['amount_minor' => $payout->amount_minor]);

                return $payout;
            }, 3);
            if ($done) {
                $paid++;
                $user = User::withoutGlobalScopes()->find($done->user_id);
                if ($user) {
                    $this->notifications->send($user, 'knowledge_reward_paid', 'You earned a Knowledge Hub reward',
                        (KnowledgeRewardPayout::KINDS[$done->kind] ?? 'Reward').': NGN '.Money::fromMinor((int) $done->amount_minor).' is now in your withdrawable earnings.',
                        ['payout_uuid' => $done->uuid, 'url' => route('commerce.wallet')]);
                }
            }
        }

        return $paid;
    }

    /**
     * Splits a fixed pool by weight with a per-person cap (water-filling), in
     * whole naira. Leftover from rounding or caps is simply not paid out.
     *
     * @param array<int,int> $weights
     * @return array<int,int> user id => amount minor
     */
    public function split(int $poolMinor, array $weights, int $maxSharePercent): array
    {
        $weights = array_filter($weights, fn (int $w) => $w > 0);
        if ($poolMinor <= 0 || $weights === []) {
            return [];
        }
        $cap = $maxSharePercent > 0 && $maxSharePercent < 100 ? intdiv($poolMinor * $maxSharePercent, 100) : $poolMinor;
        $amounts = [];
        $open = $weights;
        $remaining = $poolMinor;
        while ($open !== [] && $remaining > 0) {
            $total = array_sum($open);
            $cappedThisRound = false;
            foreach ($open as $id => $weight) {
                if ($remaining * $weight / $total >= $cap) {
                    $amounts[$id] = $cap;
                    $remaining -= $cap;
                    unset($open[$id]);
                    $cappedThisRound = true;
                }
            }
            if (! $cappedThisRound) {
                foreach ($open as $id => $weight) {
                    $amounts[$id] = (int) floor($remaining * $weight / $total);
                }
                break;
            }
        }

        // Whole naira only.
        return array_filter(array_map(fn (int $minor) => intdiv($minor, 100) * 100, $amounts), fn (int $minor) => $minor > 0);
    }

    /** @return list<int> */
    private function excludedUsers(string $scope): array
    {
        return KnowledgeRewardPayout::query()->where('scope_key', $scope)->where('status', 'excluded')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param array<int,int> $weights
     * @param array<int,array<string,mixed>> $meta
     * @return Collection<int,KnowledgeRewardPayout>
     */
    private function writeDrafts(string $kind, string $scope, array $weights, array $meta, int $poolMinor, int $maxShare, User $admin): Collection
    {
        abort_unless($admin->isSuperAdmin(), 403);

        return DB::transaction(function () use ($kind, $scope, $weights, $meta, $poolMinor, $maxShare, $admin): Collection {
            $kept = KnowledgeRewardPayout::query()->where('scope_key', $scope)->whereIn('status', ['paid', 'excluded'])->lockForUpdate()->get(['user_id', 'status', 'amount_minor']);
            KnowledgeRewardPayout::query()->where('scope_key', $scope)->where('status', 'draft')->delete();
            $skip = $kept->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $alreadyPaid = (int) $kept->where('status', 'paid')->sum('amount_minor');
            $weights = array_diff_key($weights, array_flip($skip));
            $split = $this->split(max(0, $poolMinor - $alreadyPaid), $weights, $maxShare);
            arsort($split);
            $rank = 0;
            foreach ($split as $userId => $amount) {
                KnowledgeRewardPayout::create([
                    'user_id' => $userId, 'kind' => $kind, 'scope_key' => $scope, 'rank' => ++$rank,
                    'points' => $weights[$userId] ?? 0, 'amount_minor' => $amount, 'status' => 'draft', 'metadata' => $meta[$userId] ?? [],
                ]);
            }
            AuditLog::log('knowledge.reward.calculated', $admin->id, null, null, null, ['scope' => $scope, 'recipients' => count($split), 'amount_minor' => array_sum($split)]);

            return KnowledgeRewardPayout::query()->where('scope_key', $scope)->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")->orderBy('rank')->get();
        }, 3);
    }

    /**
     * @param list<array{user_id:int,rank:int,amount:int,points:int,meta:array<string,mixed>}> $ranked
     * @return Collection<int,KnowledgeRewardPayout>
     */
    private function writeRanked(string $kind, string $scope, array $ranked, User $admin): Collection
    {
        abort_unless($admin->isSuperAdmin(), 403);

        return DB::transaction(function () use ($kind, $scope, $ranked, $admin): Collection {
            $kept = KnowledgeRewardPayout::query()->where('scope_key', $scope)->whereIn('status', ['paid', 'excluded'])->lockForUpdate()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            KnowledgeRewardPayout::query()->where('scope_key', $scope)->where('status', 'draft')->delete();
            foreach ($ranked as $row) {
                if (in_array($row['user_id'], $kept, true)) {
                    continue;
                }
                KnowledgeRewardPayout::create([
                    'user_id' => $row['user_id'], 'kind' => $kind, 'scope_key' => $scope, 'rank' => $row['rank'],
                    'points' => $row['points'], 'amount_minor' => $row['amount'], 'status' => 'draft', 'metadata' => $row['meta'],
                ]);
            }
            AuditLog::log('knowledge.reward.calculated', $admin->id, null, null, null, ['scope' => $scope, 'recipients' => count($ranked)]);

            return KnowledgeRewardPayout::query()->where('scope_key', $scope)->orderBy('rank')->get();
        }, 3);
    }
}
