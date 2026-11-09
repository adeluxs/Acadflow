<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicChallenge;
use App\Models\AuditLog;
use App\Models\KnowledgeReadAttempt;
use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeRewardPayout;
use App\Services\Commerce\PricingCatalogService;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\ReadEarnSettings;
use App\Services\Knowledge\ReadToEarnService;
use App\Services\Knowledge\RewardPoolService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Super-admin controls for Reader Pass, Read & Earn, reward pools and challenge prizes. */
class ReadEarnManagementController extends Controller
{
    public function index(Request $request, ReadEarnSettings $settings, PricingCatalogService $pricing): View
    {
        $period = now()->format('Y-m');
        $lastPeriod = now()->subMonthNoOverflow()->format('Y-m');
        $selected = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('period')) ? (string) $request->query('period') : $lastPeriod;

        $stats = KnowledgeReadAttempt::query()->where('period', $period)
            ->selectRaw("COUNT(*) AS attempts, SUM(CASE WHEN status = 'counted' THEN 1 ELSE 0 END) AS counted, SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) AS flagged, COUNT(DISTINCT user_id) AS readers, SUM(credit_minor) AS credit_minor")
            ->first();
        $flagged = KnowledgeReadAttempt::query()->where('status', KnowledgeReadAttempt::STATUS_FLAGGED)
            ->with(['user:id,first_name,last_name,email,created_at', 'publication:id,slug,title'])->latest()->limit(50)->get();
        $pools = [];
        foreach (['reader_pool', 'creator_pool'] as $kind) {
            $pools[$kind] = KnowledgeRewardPayout::query()->where('scope_key', RewardPoolService::poolScope($kind, $selected))
                ->with('user:id,first_name,last_name,email')->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")->orderBy('rank')->limit(300)->get();
        }
        $readingChallenges = KnowledgeReadingChallenge::query()->latest('starts_at')->limit(20)->get();
        $challengePayouts = KnowledgeRewardPayout::query()->whereIn('kind', ['reading_challenge', 'writing_challenge'])
            ->with('user:id,first_name,last_name,email')->orderBy('rank')->limit(500)->get()->groupBy('scope_key');
        $writingChallenges = \App\Models\KnowledgeWritingChallenge::query()->withCount('entries')->latest('starts_at')->limit(20)->get();
        $categories = \App\Models\KnowledgeCategory::query()->whereNull('university_id')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $prizeFunds = \App\Models\ChallengePrizeFund::query()->whereIn('status', ['funded', 'settled'])->with(['challenge:id,uuid,slug,title,status,results_published_at', 'funder:id,first_name,last_name,email'])
            ->latest('funded_at')->limit(30)->get();
        $passRule = $pricing->featureRule(ReadEarnSettings::PASS_FEATURE);
        $reportedQuestions = \App\Models\KnowledgeQuizQuestion::query()->where('report_count', '>', 0)->where(fn ($q) => $q->where('is_active', true)->orWhereNull('retired_at'))
            ->with(['publication' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'slug', 'title']), 'reports:id,knowledge_quiz_question_id,reason'])
            ->orderByRaw('retired_at IS NULL')->orderByDesc('report_count')->limit(30)->get();
        $quizStats = \Illuminate\Support\Facades\DB::table('knowledge_publications')->where('status', 'published')
            ->selectRaw("SUM(CASE WHEN quiz_status = 'ready' THEN 1 ELSE 0 END) AS ready, SUM(CASE WHEN quiz_status IS NULL OR quiz_status = 'pending' THEN 1 ELSE 0 END) AS waiting, SUM(CASE WHEN quiz_status = 'failed' THEN 1 ELSE 0 END) AS failed")->first();
        $awaitingApproval = \App\Models\KnowledgePublication::withoutGlobalScopes()->where('quiz_status', 'awaiting_approval')->latest('quiz_generated_at')->limit(20)->get(['id', 'slug', 'title', 'quiz_generated_at']);

        return view('admin.knowledge.read-earn', [
            'values' => $settings->all(),
            'definitions' => ReadEarnSettings::DEFINITIONS,
            'groups' => ReadEarnSettings::GROUPS,
            'moduleStatus' => FeatureAccessService::effectiveStatus(ReadEarnSettings::FEATURE),
            'period' => $period, 'selected' => $selected,
            'stats' => $stats, 'flagged' => $flagged, 'pools' => $pools,
            'readingChallenges' => $readingChallenges, 'challengePayouts' => $challengePayouts, 'writingChallenges' => $writingChallenges, 'categories' => $categories, 'prizeFunds' => $prizeFunds,
            'passRule' => $passRule, 'reasons' => ReadToEarnService::REASONS,
            'reportedQuestions' => $reportedQuestions, 'quizStats' => $quizStats, 'awaitingApproval' => $awaitingApproval,
        ]);
    }

    public function updateSettings(Request $request, ReadEarnSettings $settings): RedirectResponse
    {
        $rules = [];
        foreach (ReadEarnSettings::DEFINITIONS as $key => [$type]) {
            $rules[$key] = match ($type) {
                'boolean' => ['nullable', 'boolean'],
                'integer' => ['required', 'integer', 'min:0', 'max:1000000'],
                default => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
            };
        }
        foreach (ReadEarnSettings::LIMITS as $key => [$min, $max]) {
            $rules[$key] = ['required', 'integer', 'min:'.$min, 'max:'.$max];
        }
        $data = $request->validate($rules);
        $old = $settings->all();
        foreach (ReadEarnSettings::DEFINITIONS as $key => [$type]) {
            $value = $type === 'boolean' ? $request->boolean($key) : ($type === 'integer' ? (int) $data[$key] : Money::fromMinor(Money::toMinor((string) $data[$key])));
            SettingService::set($key, $value, $type === 'money' ? 'decimal' : $type, null, $request->user()->id);
        }
        SettingService::flushRequestCache();
        $settings->forget();
        AuditLog::log('knowledge.read_earn.settings_updated', $request->user()->id, null, null, $old, $settings->all());

        return back()->with('success', 'Read & Earn settings saved.');
    }

    public function calculatePool(Request $request, string $kind, RewardPoolService $pools): RedirectResponse
    {
        abort_unless(in_array($kind, ['reader_pool', 'creator_pool'], true), 404);
        $period = (string) $request->validate(['period' => ['required', 'string']])['period'];
        $rows = $kind === 'reader_pool' ? $pools->calculateReaderPool($period, $request->user()) : $pools->calculateCreatorPool($period, $request->user());
        $drafts = $rows->where('status', 'draft');

        return redirect()->route('admin.knowledge.read-earn.index', ['period' => $period])
            ->with('success', $drafts->count().' draft rewards totalling NGN '.number_format($drafts->sum('amount_minor') / 100).'. Review, exclude anyone suspicious, then pay.');
    }

    public function pay(Request $request, RewardPoolService $pools): RedirectResponse
    {
        $scope = (string) $request->validate(['scope_key' => ['required', 'string', 'max:80', 'regex:/^(reader_pool|creator_pool|reading_challenge|writing_challenge):[0-9-]+$/']])['scope_key'];
        $paid = $pools->pay($scope, $request->user());

        return back()->with('success', $paid.' reward'.($paid === 1 ? '' : 's').' paid into withdrawable earnings.');
    }

    public function exclude(Request $request, KnowledgeRewardPayout $payout, RewardPoolService $pools): RedirectResponse
    {
        $data = $request->validate(['excluded' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);
        $pools->setExcluded($payout, (bool) $data['excluded'], $data['note'] ?? null, $request->user());

        return back()->with('success', $data['excluded'] ? 'Excluded. Recalculate to share their amount with others.' : 'Included again. Recalculate to update amounts.');
    }

    public function reviewQuestion(Request $request, \App\Models\KnowledgeQuizQuestion $question): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:restore,remove']]);
        if ($data['decision'] === 'restore') {
            $question->forceFill(['retired_at' => null, 'report_count' => 0, 'is_active' => true])->save();
            $question->reports()->delete();
        } else {
            // Kept for the record, never asked again.
            $question->forceFill(['is_active' => false, 'retired_at' => $question->retired_at ?? now()])->save();
        }
        \App\Models\AuditLog::log('knowledge.quiz_question.'.$data['decision'], $request->user()->id, \App\Models\KnowledgeQuizQuestion::class, $question->id);

        return back()->with('success', $data['decision'] === 'restore' ? 'Question restored.' : 'Question removed from the quiz.');
    }

    public function approveQuizBank(Request $request, \App\Models\KnowledgePublication $publication): RedirectResponse
    {
        $publication->quizQuestions()->where('source', 'ai')->whereNull('retired_at')->update(['is_active' => true]);
        \Illuminate\Support\Facades\DB::table('knowledge_publications')->where('id', $publication->id)->update(['quiz_status' => 'ready']);

        return back()->with('success', 'AI questions approved for "'.$publication->title.'".');
    }

    public function settleEscrow(Request $request, \App\Models\ChallengePrizeFund $fund, \App\Services\Knowledge\ChallengePrizeService $prizes): RedirectResponse
    {
        $prizes->settle($fund, $request->user());

        return back()->with('success', 'Prize fund settled.');
    }

    public function reviewAttempt(Request $request, KnowledgeReadAttempt $attempt, ReadToEarnService $earn): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $earn->review($attempt, $request->user(), $data['decision'] === 'approve');

        return back()->with('success', 'Decision recorded.');
    }

    public function storeChallenge(Request $request): RedirectResponse
    {
        $challenge = KnowledgeReadingChallenge::create($this->challengeData($request) + ['created_by' => $request->user()->id]);
        AuditLog::log('knowledge.reading_challenge.created', $request->user()->id, KnowledgeReadingChallenge::class, $challenge->id, null, $challenge->only(['title', 'status', 'prize_pool_minor']));

        return back()->with('success', 'Reading Challenge saved.');
    }

    public function updateChallenge(Request $request, KnowledgeReadingChallenge $challenge): RedirectResponse
    {
        $old = $challenge->only(['title', 'status', 'prizes', 'starts_at', 'ends_at']);
        $challenge->update($this->challengeData($request));
        AuditLog::log('knowledge.reading_challenge.updated', $request->user()->id, KnowledgeReadingChallenge::class, $challenge->id, $old, $challenge->only(['title', 'status', 'prizes']));

        return back()->with('success', 'Reading Challenge updated.');
    }

    public function calculateReadingChallenge(Request $request, KnowledgeReadingChallenge $challenge, RewardPoolService $pools): RedirectResponse
    {
        $rows = $pools->calculateReadingChallenge($challenge, $request->user());
        if ($challenge->status === 'active') {
            $challenge->update(['status' => 'completed']);
        }

        return back()->with('success', $rows->where('status', 'draft')->count().' winners drafted. Check them, then pay.');
    }

    /** @return array<string,mixed> */
    private function challengeData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'prizes' => ['required', 'string', 'max:500', 'regex:/^\s*\d{1,9}(?:\s*,\s*\d{1,9})*\s*$/'],
            'min_passed_reads' => ['required', 'integer', 'min:1', 'max:1000'],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'status' => ['required', Rule::in(KnowledgeReadingChallenge::STATUSES)],
        ]);
        $prizes = $this->prizeList($data['prizes']);

        return [
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
            'prizes' => $prizes, 'prize_pool_minor' => array_sum($prizes),
            'min_passed_reads' => (int) $data['min_passed_reads'], 'university_id' => $data['university_id'] ?? null, 'status' => $data['status'],
        ];
    }

    /** "25000, 15000, 10000" (naira) => minor amounts by rank. @return list<int> */
    private function prizeList(string $prizes): array
    {
        return array_values(array_map(fn ($amount) => Money::toMinor(trim($amount)), array_filter(explode(',', $prizes), fn ($amount) => trim($amount) !== '')));
    }
}
