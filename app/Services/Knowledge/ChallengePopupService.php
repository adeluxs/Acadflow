<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeWritingChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The "challenges are live" pop-up: shown once after login and then at most once an hour,
 * never while the user is reading or writing a publication (or already on a challenge page).
 */
class ChallengePopupService
{
    public const SESSION_KEY = 'challenge_popup_seen_at';

    /** Pages where the pop-up would interrupt reading, writing or is already redundant. */
    private const QUIET_ROUTES = [
        'knowledge.show', 'knowledge.manage.create', 'knowledge.manage.edit', 'knowledge.manage.show', 'knowledge.manage.preview',
        'knowledge.series.create', 'knowledge.series.edit', 'knowledge.rewards.*', 'knowledge.writing.*', 'onboarding.*', 'login', 'logout', 'register',
    ];

    public function __construct(private readonly ReadEarnSettings $settings) {}

    /**
     * What the layout needs, or null when nothing should be shown on this page.
     *
     * @return array{writing: \Illuminate\Support\Collection, reading: \Illuminate\Support\Collection, delay: int}|null
     */
    public function forRequest(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! $this->settings->bool('knowledge_challenge_popup_enabled') || ! $this->settings->moduleEnabled($user->university_id)) {
            return null;
        }
        if ($request->routeIs(...self::QUIET_ROUTES) || ! $request->isMethod('GET')) {
            return null;
        }
        $challenges = $this->live($user);
        if ($challenges['writing']->isEmpty() && $challenges['reading']->isEmpty()) {
            return null;
        }
        $every = max(5, $this->settings->int('knowledge_challenge_popup_minutes')) * 60;
        $seenAt = (int) $request->session()->get(self::SESSION_KEY, 0);
        // Due now after login (nothing seen this session); otherwise when the interval is up,
        // which a timer on the page catches if the user stays on one page that long.
        $delay = $seenAt === 0 ? 0 : max(0, $seenAt + $every - now()->timestamp);

        return $challenges + ['delay' => $delay];
    }

    public function markSeen(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
    }

    /** Live and upcoming challenges, cached briefly so the layout adds no queries to most page views. */
    public function live(User $user): array
    {
        return Cache::remember('knowledge-challenge-popup:'.($user->university_id ?? 0), 300, function () use ($user): array {
            return [
                'writing' => KnowledgeWritingChallenge::query()->where('status', 'published')->whereNull('results_published_at')
                    ->where('submission_deadline', '>=', now())->where('starts_at', '<=', now()->addDays(14))
                    ->orderBy('submission_deadline')->limit(2)->get(),
                'reading' => KnowledgeReadingChallenge::query()->where('status', 'active')->where('ends_at', '>=', now())
                    ->where('starts_at', '<=', now()->addDays(14))
                    ->where(fn ($q) => $q->whereNull('university_id')->orWhere('university_id', $user->university_id))
                    ->orderBy('ends_at')->limit(2)->get(),
            ];
        });
    }
}
