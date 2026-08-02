<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;
use App\Enums\AiMode;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;

/**
 * The AI Manager - the ONLY gateway for AI operations across AcadFlow.
 *
 * Responsibilities (Phase 2):
 *  - Receive AI requests from feature modules
 *  - Determine the requested feature
 *  - Forward to the AI Router (which picks the engine)
 *  - Enforce usage limits (daily / monthly / max cost)
 *  - Log requests & track usage (via AiAnalytics)
 *  - Return standardized AiResponse objects
 *
 * Hybrid flow (Phase 5):
 *  cache -> rule engine -> if rule engine answered return it
 *  else call provider (by priority) -> on provider failure fall back to rule engine.
 */
class AiManager
{
    public function __construct(
        protected AiRouter $router,
        protected RuleEngine $engine,
        protected AiCache $cache,
        protected AiAnalytics $analytics,
    ) {}

    /**
     * Main entry point used by every AI feature module.
     *
     * @param  string  $feature  Feature key (e.g. 'submission_validator')
     * @param  array  $payload  Feature-specific context (text, submission, type...)
     * @param  User|null  $user  Acting user (for scoping & analytics)
     * @param  string|null  $scope  Optional cache scope (e.g. submission uuid)
     */
    public function analyze(string $feature, array $payload, ?User $user = null, ?string $scope = null): AiResponse
    {
        $mode = $this->router->mode();

        if ($mode === AiMode::DISABLED) {
            return new AiResponse(
                source: 'disabled',
                feature: $feature,
                success: false,
                summary: 'AI features are currently disabled.',
            );
        }

        // Honor globally-disabled external AI: force rule-based behaviour.
        if (! (bool) SettingService::get('ai_enable_external_ai', config('ai.enable_external_ai', false))) {
            $mode = AiMode::RULE_BASED;
        }

        // 0. Usage-limit guard (Phase 10): never exceed configured budget.
        if ($this->limitsExceeded($user)) {
            $mode = AiMode::RULE_BASED;
        }

        // Inject layout requirements for the submission validator so
        // LayoutRulePack can check against explicit institutional or
        // per-lecturer requirements rather than generic heuristics.
        if ($feature === 'submission_validator') {
            $payload['layout_requirements'] = $this->resolveLayoutRequirements($user);
        }

        // 1. Cache check
        if ($cached = $this->cache->get($feature, $payload, $scope)) {
            $cached->cached = true;
            $this->analytics->record($this->analyticsContext($feature, $mode, $cached, $user, true));

            return $cached;
        }

        // 2. Rule engine first (cheapest)
        $ruleResponse = $this->engine->run($feature, $payload);

        $useProvider = $mode === AiMode::PROVIDER
            || ($mode === AiMode::HYBRID && ! $this->ruleEngineSufficient($ruleResponse));

        $response = $ruleResponse;

        if ($useProvider) {
            $response = $this->callProvider($feature, $payload, $ruleResponse);
        }

        $response->cached = false;
        $this->cache->put($feature, $payload, $response, $scope);

        // Account one request against the usage counters (audit B3/B4). Cost is
        // the realized cost from the provider, or ~0 for the rule engine.
        $this->accountRequest($user, (float) ($response->cost ?? $this->estimateRuleCost()));

        $this->analytics->record($this->analyticsContext($feature, $mode, $response, $user, false));

        return $response;
    }

    /**
     * Invalidate cached AI results for a submission scope (Phase 7). Call this
     * whenever a submission's document, version or report changes.
     */
    public function invalidateScope(string $scope): void
    {
        $this->cache->forgetScope($scope);
    }

    /**
     * Invalidate all cached AI results for a feature (e.g. when its rule pack or
     * settings change).
     */
    public function invalidateFeature(string $feature): void
    {
        $this->cache->forgetFeature($feature);
    }

    /**
     * Whether the rule engine produced an acceptable answer (hybrid gate).
     *
     * Hybrid flow (Phase 5): run the rule engine first (cheapest). If it found
     * actionable issues we keep its structured result. If it found no issues
     * ("clean") and ai_hybrid_escalate_when_clean is enabled, escalate to the
     * external provider for richer generative analysis. The rule engine is
     * always treated as a successful answer, so the user never sees an error.
     */
    protected function ruleEngineSufficient(AiResponse $response): bool
    {
        // If the rule engine raised actionable issues, its result is sufficient.
        if (! empty($response->issues)) {
            return true;
        }

        // A clean rule result stays sufficient unless escalation is enabled.
        return ! (bool) SettingService::get('ai_hybrid_escalate_when_clean', false);
    }

    /**
     * Call providers in configured priority order, falling back through the
     * chain and finally to the rule engine.
     */
    protected function callProvider(string $feature, array $payload, AiResponse $ruleResponse): AiResponse
    {
        $names = $this->providerChain();

        foreach ($names as $name) {
            $provider = $this->router->provider($name);

            if (! $provider instanceof AiProviderInterface || ! $provider->isAvailable()) {
                continue;
            }

            try {
                $providerResponse = $provider->handle($feature, $payload);

                if ($providerResponse->success) {
                    // Stamp the real provider source so analytics correctly
                    // attribute cost/failure rate (audit B11).
                    $this->accountProviderCost($providerResponse->cost);

                    return $providerResponse;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 3. Fallback to rule engine (must never error). Mark the source as a
        // rule-engine fallback (not a clean rule_engine hit) so the failure rate
        // and source attribution stay accurate (audit B11).
        return $ruleResponse->withData([
            'source' => 'rule_engine_fallback',
            'note' => 'Provider(s) unavailable/failed; rule engine fallback used.',
        ]);
    }

    /**
     * Build the ordered provider chain from provider_priority + fallback_provider.
     */
    protected function providerChain(): array
    {
        $priority = config('ai.provider_priority', []);
        $fallback = $this->router->fallbackProviderName();

        $chain = array_values(array_unique(array_merge($priority, [$fallback])));

        // Never escalate to the rule-based engine as a "provider" here.
        return array_values(array_filter($chain, fn ($n) => $n !== 'rule_based'));
    }

    /**
     * Check daily / monthly request limits and monthly cost ceiling.
     *
     * Independent of the logging toggle (audit B4): budget enforcement must not
     * be disabled simply because usage logging is off.
     */
    protected function limitsExceeded(?User $user): bool
    {
        $daily = (int) SettingService::get('ai_daily_request_limit', config('ai.daily_request_limit', 1000));
        $monthly = (int) SettingService::get('ai_monthly_request_limit', config('ai.monthly_request_limit', 30000));
        $maxCost = (float) SettingService::get('ai_max_cost', config('ai.max_cost', 100));

        if ($daily <= 0 && $monthly <= 0 && $maxCost <= 0) {
            return false;
        }

        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');

        $dayCount = (int) Cache::get("{$scope}:day:{$today}", 0);
        $monthCount = (int) Cache::get("{$scope}:month:{$month}", 0);
        $monthCost = (float) Cache::get("{$scope}:cost:{$month}", 0.0);

        if ($daily > 0 && $dayCount >= $daily) {
            return true;
        }
        if ($monthly > 0 && $monthCount >= $monthly) {
            return true;
        }
        if ($maxCost > 0 && $monthCost >= $maxCost) {
            return true;
        }

        return false;
    }

    /**
     * Record one request against the usage counters (called once per real
     * request, after the response is produced) and add the realized cost.
     */
    protected function accountRequest(?User $user, float $cost): void
    {
        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');

        Cache::increment("{$scope}:day:{$today}");
        Cache::increment("{$scope}:month:{$month}");
        Cache::increment("{$scope}:cost:{$month}", $cost);
    }

    /**
     * Negligible cost for a rule-engine call (used for limit accounting).
     */
    protected function estimateRuleCost(): float
    {
        return 0.0;
    }

    /**
     * Add a provider's realized cost to the monthly ceiling tracker (audit B3).
     */
    protected function accountProviderCost(?float $cost): void
    {
        if (! ($cost > 0)) {
            return;
        }

        $month = now()->format('Y-m');
        Cache::increment("ai:limits:global:cost:{$month}", (float) $cost);
    }

    /**
     * Resolve the effective layout requirements for a submission analysis.
     *
     * Precedence:
     *  1. Per-lecturer preferences (if the requesting user is a lecturer and has
     *     saved preferences)
     *  2. Institution-level defaults from SettingService / config
     *  3. Hardcoded empty array (no requirements enforced)
     */
    protected function resolveLayoutRequirements(?User $user): array
    {
        $defaults = config('ai.layout_requirements', []);

        // Institution-level overrides from tools_settings
        $institution = [
            'required_fonts' => SettingService::get('ai_layout_required_fonts', $defaults['required_fonts'] ?? null),
            'page_size' => SettingService::get('ai_layout_page_size', $defaults['page_size'] ?? null),
            'min_margin_inches' => SettingService::get('ai_layout_min_margin_inches', $defaults['min_margin_inches'] ?? null),
            'line_spacing' => SettingService::get('ai_layout_line_spacing', $defaults['line_spacing'] ?? null),
            'min_font_size_pt' => SettingService::get('ai_layout_min_font_size', $defaults['min_font_size_pt'] ?? null),
            'require_page_numbering' => SettingService::get('ai_layout_require_page_numbering', $defaults['require_page_numbering'] ?? false),
            'require_institution_branding' => SettingService::get('ai_layout_require_branding', $defaults['require_institution_branding'] ?? false),
        ];

        // Normalize JSON-encoded arrays from SettingService
        foreach (['required_fonts'] as $jsonKey) {
            if (is_string($institution[$jsonKey])) {
                $decoded = json_decode($institution[$jsonKey], true);
                $institution[$jsonKey] = is_array($decoded) ? $decoded : $institution[$jsonKey];
            }
        }

        // Lecturer overrides take precedence over institution defaults
        if ($user && $user->isLecturer()) {
            $prefs = \App\Models\LecturerLayoutPreference::where('user_id', $user->id)->first();

            if ($prefs) {
                foreach (array_keys($institution) as $key) {
                    if ($prefs->$key !== null && $prefs->$key !== false && $prefs->$key !== '') {
                        $institution[$key] = $prefs->$key;
                    }
                }
            }
        }

        // Filter out empty/null requirements so LayoutRulePack knows when
        // to fall back to heuristics vs. strict checking.
        return array_filter($institution, fn ($v) => $v !== null && $v !== '' && $v !== false);
    }

    protected function analyticsContext(string $feature, AiMode $mode, AiResponse $response, ?User $user, bool $cached): array
    {
        // A request is "rule engine" when served by the rule engine or its
        // fallback (audit B11): savings are counted only when no paid provider
        // was actually used.
        $source = $response->source ?? 'rule_engine';
        $isRule = in_array($source, ['rule_engine', 'rule_engine_fallback'], true);

        return [
            'user_id' => $user?->id,
            'university_id' => $user?->university_id,
            'department_id' => $user?->department_id,
            'feature' => $feature,
            'mode' => $mode->value,
            'source' => $source,
            'cached' => $cached,
            'success' => $response->success,
            'processing_time' => $response->processingTime,
            'cost' => $response->cost,
            'estimated_savings' => $isRule ? ($response->cost ?? 0) : 0,
            'score' => $response->score,
            'issue_count' => count($response->issues),
        ];
    }
}
