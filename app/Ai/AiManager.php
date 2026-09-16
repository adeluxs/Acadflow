<?php

namespace App\Ai;

use App\Ai\Contracts\AiRequest;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;
use App\Enums\AiMode;
use App\Models\User;
use App\Services\Ai\AiPromptService;
use App\Services\Ai\AiResponseSchemaValidator;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\Commerce\AiUsageBillingService;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single runtime gateway for AcadFlow AI operations.
 *
 * Runtime contract:
 *   AI Settings -> AiRuntimeConfigService -> AiRouter -> provider adapter.
 *
 * Provider mode never silently degrades to the RuleEngine. Hybrid mode may use
 * an explicit rule fallback only when the feature allows it, and that event is
 * labeled/observed as a fallback rather than pretending an external provider
 * answered the request.
 */
class AiManager
{
    public function __construct(
        protected AiRouter $router,
        protected RuleEngine $engine,
        protected AiCache $cache,
        protected AiAnalytics $analytics,
        protected AiPromptService $prompts,
        protected AiResponseSchemaValidator $responseValidator,
        protected AiRuntimeConfigService $runtime,
        protected AiUsageBillingService $billing,
    ) {}

    public function analyze(
        string $feature,
        array $payload,
        ?User $user = null,
        ?string $scope = null,
        ?string $requestId = null,
    ): AiResponse {
        // Keep backwards compatibility with callers that pass a logical request ID
        // as a named argument. An explicit argument is authoritative and is copied
        // into the internal request envelope so the full lifecycle preserves it.
        if ($requestId !== null && trim($requestId) !== '') {
            $payload['_ai_request_id'] = trim($requestId);
        }

        return $this->analyzeRequest(new AiRequest($feature, $payload, $user, $scope));
    }

    public function analyzeRequest(AiRequest $request): AiResponse
    {
        $feature = $request->feature;
        $payload = $request->payload;
        $user = $request->user;
        $scope = $request->scope;
        $universityId = $request->universityId();
        $requestId = $this->resolveRequestId($payload);
        $lifecycleStartedAt = $this->requestStartedAt();
        $deadlineAt = $lifecycleStartedAt + $this->lifecycleBudgetSeconds();
        $this->lifecycleLog('info', 'AI request received.', $requestId, $feature, $lifecycleStartedAt, [
            'stage' => 'request_received',
            'university_id' => $universityId,
        ]);

        if (! $this->runtime->featureEnabled($feature, $universityId)) {
            return $this->recordAndReturn(new AiResponse(
                source: 'disabled', feature: $feature, success: false,
                summary: 'This AI feature is disabled for the current institution.',
                requestId: $requestId, errorCode: 'AI_FEATURE_DISABLED', confidence: 0.0,
            ), $feature, AiMode::DISABLED, $user, false);
        }

        $mode = $this->router->mode($universityId);
        if ($mode === AiMode::DISABLED) {
            return $this->recordAndReturn(new AiResponse(
                source: 'disabled', feature: $feature, success: false,
                summary: 'AI assistance is currently unavailable.', requestId: $requestId,
                errorCode: 'AI_DISABLED', confidence: 0.0,
            ), $feature, $mode, $user, false);
        }

        $payload['_tenant_university_id'] = $universityId;
        $payload['_ai_request_id'] = $requestId;
        $payload['_ai_routing_fingerprint'] = $this->runtime->routingFingerprint($feature, $universityId);
        $payload['_ai_deadline_at'] = $deadlineAt;

        if ($feature === 'submission_validator') {
            $payload['layout_requirements'] = $this->resolveLayoutRequirements($user, $universityId);
        }

        $payload = $this->prompts->enrich($feature, $payload, $user);

        try {
            $cached = $this->cache->get($feature, $payload, $scope, $universityId);
        } catch (\Throwable $e) {
            $cached = null;
            $this->lifecycleLog('warning', 'AI cache read failed; continuing without cache.', $requestId, $feature, $lifecycleStartedAt, [
                'stage' => 'cache_read',
                'error_type' => $e::class,
            ]);
        }
        if ($cached) {
            // Cached provider answers are still successful user AI interactions.
            // They use the current logical request ID and pass through the same
            // pricing/free-allowance lifecycle, but incur zero new provider cost.
            $cached = $cached->withData([
                'request_id' => $requestId,
                'metadata' => [
                    'provider_cost_micro_usd' => 0,
                    'cache_reuse' => true,
                ],
            ]);
            $cached->cached = true;

            $billableCachedProviderResponse = ! in_array($cached->source, ['rule_engine', 'rule_engine_fallback', 'disabled', 'limit_exceeded', 'unavailable'], true)
                && $cached->provider !== null;
            if ($billableCachedProviderResponse) {
                try {
                    $reservation = $this->billing->reserve($user, $feature, $requestId);
                    if (! empty($reservation['already_reserved'])) {
                        return $this->recordAndReturn(new AiResponse(
                            source: 'billing', feature: $feature, success: false,
                            summary: 'This AI request is already in progress.',
                            requestId: $requestId, errorCode: 'AI_REQUEST_IN_PROGRESS', confidence: 0.0,
                            metadata: ['cache_reuse' => true],
                        ), $feature, $mode, $user, true, false);
                    }
                    if (empty($reservation['already_completed'])) {
                        $this->billing->settle($user, $feature, $cached, $reservation);
                        try { $this->accountRequest($user, $cached); } catch (\Throwable) {}
                    }
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $message = $e->validator->errors()->first('ai') ?: 'AI billing blocked this request.';
                    $insufficient = str_contains(strtolower($message), 'balance') || str_contains(strtolower($message), 'allowance');
                    return $this->recordAndReturn(new AiResponse(
                        source: 'billing', feature: $feature, success: false,
                        summary: $message,
                        requestId: $requestId, errorCode: $insufficient ? 'AI_INSUFFICIENT_BALANCE' : 'AI_BILLING_BLOCKED', confidence: 0.0,
                        metadata: array_filter(['action' => $insufficient ? 'add_funds' : null, 'cache_reuse' => true]),
                    ), $feature, $mode, $user, true, false);
                } catch (\Throwable $e) {
                    // A valid cached answer has already been delivered. If settlement
                    // bookkeeping fails, release any reserved user/institution funds
                    // exactly once, then keep the request flagged for reconciliation.
                    // This preserves the historical resilience contract: post-response
                    // billing failures must not turn a valid answer into a failed AI call.
                    $this->safeReleaseReservation($user, $reservation ?? [], $requestId, $feature);
                    $this->safeMarkReconciliationRequired($requestId, $feature, 'cached_settlement_failed');
                    Log::channel('ai_provider')->warning('Cached AI billing failed after a valid cached response; answer preserved.', [
                        'request_id' => $requestId,
                        'feature' => $feature,
                        'error_type' => $e::class,
                    ]);
                    $cached = $cached->withData(['metadata' => [
                        'billing_status' => 'reconciliation_required',
                        'billing_settlement_pending' => true,
                    ]]);
                }
            }
            $this->lifecycleLog('info', 'AI request completed from cache.', $requestId, $feature, $lifecycleStartedAt, ['stage' => 'response_ready']);
            return $this->recordAndReturn($cached, $feature, $mode, $user, true, false);
        }

        // Provider AI is genuinely provider-only at the response engine layer.
        // Do not execute the deterministic Rule Engine merely to discard its
        // result. Hybrid / Rule-Based modes intentionally execute rules.
        if ($mode === AiMode::PROVIDER) {
            $response = $this->limitsExceeded($user)
                ? new AiResponse(
                    source: 'limit_exceeded', feature: $feature, success: false,
                    summary: 'The configured AI usage limit has been reached.', requestId: $requestId,
                    errorCode: 'AI_USAGE_LIMIT_REACHED', confidence: 0.0,
                    metadata: ['routing' => $this->router->route($feature, $universityId)],
                )
                : $this->callProviderChain($feature, $payload, $universityId, $requestId, $user);
        } else {
            $ruleResponse = $this->engine->run($feature, $payload)->withData([
                'request_id' => $requestId,
                'provider' => null,
                'model' => 'rule-engine',
                'metadata' => ['routing' => $this->router->route($feature, $universityId)],
            ]);

            if ($mode === AiMode::RULE_BASED) {
                $response = $ruleResponse->withData(['source' => 'rule_engine']);
            } elseif ($this->limitsExceeded($user)) {
                $response = $this->runtime->featureRuleFallbackEnabled($feature, $universityId)
                    ? $ruleResponse->withData([
                        'source' => 'rule_engine_fallback',
                        'fallback_used' => true,
                        'error_code' => 'AI_USAGE_LIMIT_REACHED',
                        'note' => 'External AI usage limit reached; explicit Hybrid rule fallback used.',
                    ])
                    : new AiResponse(
                        source: 'limit_exceeded', feature: $feature, success: false,
                        summary: 'The configured AI usage limit has been reached.', requestId: $requestId,
                        errorCode: 'AI_USAGE_LIMIT_REACHED', confidence: 0.0,
                        metadata: ['routing' => $this->router->route($feature, $universityId)],
                    );
            } else {
                $response = $this->runHybrid($feature, $payload, $ruleResponse, $universityId, $requestId, $user);
            }
        }

        $response->cached = false;
        if ($response->success) {
            try {
                $this->cache->put($feature, $payload, $response, $scope, $universityId);
            } catch (\Throwable $e) {
                $this->lifecycleLog('warning', 'AI cache write failed after successful response.', $requestId, $feature, $lifecycleStartedAt, [
                    'stage' => 'cache_write',
                    'error_type' => $e::class,
                ]);
            }
        }

        try {
            $this->accountRequest($user, $response);
        } catch (\Throwable $e) {
            $this->lifecycleLog('warning', 'AI usage counter update failed after response.', $requestId, $feature, $lifecycleStartedAt, [
                'stage' => 'usage_accounting',
                'error_type' => $e::class,
            ]);
        }
        $this->lifecycleLog('info', 'AI response ready for caller.', $requestId, $feature, $lifecycleStartedAt, [
            'stage' => 'response_ready',
            'success' => $response->success,
            'provider' => $response->provider,
            'model' => $response->model,
            'error_code' => $response->errorCode,
        ]);
        return $this->recordAndReturn($response, $feature, $mode, $user, false, false);
    }

    public function invalidateScope(string $scope): void { $this->cache->forgetScope($scope); }
    public function invalidateFeature(string $feature): void { $this->cache->forgetFeature($feature); }
    public function invalidateAll(): void { $this->cache->forgetAll(); }

    protected function runHybrid(string $feature, array $payload, AiResponse $ruleResponse, ?int $universityId, string $requestId, ?User $user = null): AiResponse
    {
        $providerFirst = in_array($feature, (array) config('ai.provider_first_features', []), true);
        $escalateWhenClean = $this->runtime->hybridEscalateWhenClean($universityId);
        $ruleHasIssues = $ruleResponse->issues !== [];

        // Generative/reasoning features should actually use the selected provider
        // in Hybrid mode. Deterministic validators can keep a strong rule result
        // unless the administrator requested escalation when clean.
        if ($providerFirst || (! $ruleHasIssues && $escalateWhenClean)) {
            $payload['_rule_context'] = [
                'summary' => $ruleResponse->summary,
                'score' => $ruleResponse->score,
                'issues' => $ruleResponse->issues,
            ];
            $provider = $this->callProviderChain($feature, $payload, $universityId, $requestId, $user);
            if ($provider->success) return $provider;

            if ($this->runtime->featureRuleFallbackEnabled($feature, $universityId)) {
                return $ruleResponse->withData([
                    'source' => 'rule_engine_fallback',
                    'fallback_used' => true,
                    'fallback_provider' => $provider->provider,
                    'error_code' => $provider->errorCode ?: 'AI_ALL_PROVIDERS_FAILED',
                    'note' => 'Configured providers failed; explicit Hybrid rule fallback used.',
                    'metadata' => array_merge($provider->metadata, ['provider_failure_summary' => $provider->summary]),
                ]);
            }

            return $provider;
        }

        return $ruleResponse->withData(['source' => 'rule_engine']);
    }

    protected function callProviderChain(string $feature, array $payload, ?int $universityId, string $requestId, ?User $user = null): AiResponse
    {
        $chain = $this->router->providerChain($feature, $universityId);
        $route = $this->router->route($feature, $universityId);
        $attempts = [];
        $last = null;
        $reservation = ['source' => 'platform', 'charge_minor' => 0, 'request_id' => $requestId];

        if ($chain === []) {
            return new AiResponse(
                source: 'unavailable', feature: $feature, success: false,
                summary: 'No enabled and configured external AI provider is selected for this feature.',
                requestId: $requestId, errorCode: 'AI_INVALID_CONFIGURATION', confidence: 0.0,
                metadata: ['routing' => $route, 'provider_attempts' => []],
            );
        }

        try {
            $reservation = $this->billing->reserve($user, $feature, $requestId);
            if (! empty($reservation['already_completed'])) {
                return new AiResponse(
                    source: 'billing', feature: $feature, success: false,
                    summary: 'This AI request has already been processed. Submit a new request.',
                    requestId: $requestId, errorCode: 'AI_DUPLICATE_REQUEST', confidence: 0.0,
                    metadata: ['routing' => $route],
                );
            }
            if (! empty($reservation['already_reserved'])) {
                return new AiResponse(
                    source: 'billing', feature: $feature, success: false,
                    summary: 'This AI request is already in progress.',
                    requestId: $requestId, errorCode: 'AI_REQUEST_IN_PROGRESS', confidence: 0.0,
                    metadata: ['routing' => $route],
                );
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = $e->validator->errors()->first('ai') ?: 'AI billing blocked this request.';
            $insufficient = str_contains(strtolower($message), 'balance') || str_contains(strtolower($message), 'allowance');
            return new AiResponse(
                source: 'billing', feature: $feature, success: false,
                summary: $message,
                requestId: $requestId, errorCode: $insufficient ? 'AI_INSUFFICIENT_BALANCE' : 'AI_BILLING_BLOCKED', confidence: 0.0,
                metadata: array_filter(['routing' => $route, 'action' => $insufficient ? 'add_funds' : null]),
            );
        }

        foreach ($chain as $index => $entry) {
            $providerName = $entry['provider'];
            $model = $entry['model'];

            if (! $this->router->providerSupportsFeature($providerName, $feature)) {
                $attempts[] = [
                    'provider' => $providerName, 'model' => $model, 'role' => $entry['role'],
                    'status' => 'incompatible_capabilities',
                    'required_capabilities' => $this->router->requiredCapabilities($feature),
                ];
                $last = new AiResponse(
                    source: $providerName, feature: $feature, success: false,
                    summary: 'The selected provider does not support the capabilities required by this AI feature.',
                    requestId: $requestId, provider: $providerName, model: $model,
                    errorCode: 'AI_PROVIDER_INCOMPATIBLE', confidence: 0.0,
                );
                continue;
            }

            if (! $this->runtime->providerEnabled($providerName, $universityId)
                || ! $this->runtime->providerConfigurationComplete($providerName, $universityId)) {
                $attempts[] = ['provider' => $providerName, 'model' => $model, 'role' => $entry['role'], 'status' => 'configuration_incomplete'];
                $last = new AiResponse(
                    source: $providerName, feature: $feature, success: false,
                    summary: 'Provider configuration is incomplete or disabled.', requestId: $requestId,
                    provider: $providerName, model: $model, errorCode: 'AI_INVALID_CONFIGURATION', confidence: 0.0,
                );
                continue;
            }

            if ($this->remainingBudgetSeconds($payload) <= $this->postProviderReserveSeconds()) {
                $last = new AiResponse(
                    source: 'timeout', feature: $feature, success: false,
                    summary: 'The AI request reached its safe processing time budget before another provider attempt could start.',
                    requestId: $requestId, provider: $providerName, model: $model,
                    errorCode: 'AI_REQUEST_TIMEOUT', confidence: 0.0,
                );
                $attempts[] = ['provider' => $providerName, 'model' => $model, 'role' => $entry['role'], 'status' => 'budget_exhausted'];
                break;
            }

            $provider = $this->router->provider($providerName, $universityId, $model);
            if (! $provider->isAvailable()) {
                $attempts[] = ['provider' => $providerName, 'model' => $model, 'role' => $entry['role'], 'status' => 'unavailable'];
                continue;
            }

            $providerResponse = $provider->handle($feature, $payload)->withData(['request_id' => $requestId]);
            $attempts[] = [
                'provider' => $providerName,
                'model' => $model,
                'role' => $entry['role'],
                'status' => $providerResponse->success ? 'success' : 'failed',
                'error_code' => $providerResponse->errorCode,
            ];

            if ($providerResponse->success) {
                $schema = (array) data_get($payload, '_prompt.response_schema', []);
                $schemaErrors = $this->responseValidator->errors($providerResponse, $schema);
                if ($schemaErrors !== []) {
                    $attempts[array_key_last($attempts)]['status'] = 'invalid_response_schema';
                    $attempts[array_key_last($attempts)]['schema_errors'] = $schemaErrors;
                    $last = new AiResponse(
                        source: $providerName, feature: $feature, success: false,
                        summary: 'The provider returned an invalid structured response.', requestId: $requestId,
                        provider: $providerName, model: $model, errorCode: 'AI_INVALID_PROVIDER_RESPONSE', confidence: 0.0,
                    );
                    continue;
                }

                $successful = $providerResponse->withData([
                    'provider' => $providerName,
                    'model' => $model,
                    'fallback_used' => $index > 0,
                    'fallback_provider' => $index > 0 ? $providerName : null,
                    'prompt_version_id' => data_get($payload, '_prompt.version_id'),
                    'prompt_version' => data_get($payload, '_prompt.version'),
                    'schema_validated' => $schema !== [],
                    'metadata' => [
                        'routing' => $route,
                        'provider_attempts' => $attempts,
                        'selected_role' => $entry['role'],
                    ],
                ]);
                // Once a provider has returned a valid answer, optional/post-response
                // bookkeeping must never turn that answer into AI_REQUEST_FAILED.
                try {
                    $this->billing->settle($user, $feature, $successful, $reservation);
                } catch (\Throwable $e) {
                    // The provider response is valid and must reach the user even when
                    // post-provider accounting fails. Release the reservation exactly
                    // once (the legacy resilience contract), then mark the delivered
                    // request for reconciliation so duplicate request IDs stay blocked
                    // and operations can audit the failed settlement.
                    $this->safeReleaseReservation($user, $reservation, $requestId, $feature);
                    $this->safeMarkReconciliationRequired($requestId, $feature, 'provider_settlement_failed');
                    Log::channel('ai_provider')->warning('AI billing settlement failed after a valid provider response; answer preserved.', [
                        'request_id' => $requestId,
                        'feature' => $feature,
                        'provider' => $providerName,
                        'model' => $model,
                        'error_type' => $e::class,
                    ]);
                    $successful = $successful->withData([
                        'metadata' => [
                            'billing_status' => 'reconciliation_required',
                            'billing_settlement_pending' => true,
                        ],
                    ]);
                }
                return $successful;
            }

            $last = $providerResponse;
        }

        $this->safeReleaseReservation($user, $reservation, $requestId, $feature);

        return new AiResponse(
            source: 'unavailable', feature: $feature, success: false,
            summary: 'All configured AI providers are currently unavailable.', requestId: $requestId,
            provider: $last?->provider, model: $last?->model,
            fallbackUsed: count($attempts) > 1, fallbackProvider: $last?->provider,
            errorCode: $last?->errorCode ?: 'AI_ALL_PROVIDERS_FAILED', confidence: 0.0,
            metadata: ['routing' => $route, 'provider_attempts' => $attempts],
        );
    }

    protected function limitsExceeded(?User $user): bool
    {
        $daily = $this->runtime->dailyRequestLimit($user?->university_id);
        $monthly = $this->runtime->monthlyRequestLimit($user?->university_id);
        $maxCostMicroUsd = $this->runtime->maxMonthlyCostMicroUsd($user?->university_id);
        if ($daily <= 0 && $monthly <= 0 && $maxCostMicroUsd <= 0) return false;

        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        return ($daily > 0 && (int) Cache::get("{$scope}:day:{$today}", 0) >= $daily)
            || ($monthly > 0 && (int) Cache::get("{$scope}:month:{$month}", 0) >= $monthly)
            || ($maxCostMicroUsd > 0 && (int) Cache::get("{$scope}:cost_micro_usd:{$month}", 0) >= $maxCostMicroUsd);
    }

    protected function accountRequest(?User $user, AiResponse $response): void
    {
        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        Cache::increment("{$scope}:day:{$today}");
        Cache::increment("{$scope}:month:{$month}");
        $costMicroUsd = max(0, (int) ($response->metadata['provider_cost_micro_usd'] ?? 0));
        if ($costMicroUsd > 0) Cache::increment("{$scope}:cost_micro_usd:{$month}", $costMicroUsd);
    }

    protected function resolveLayoutRequirements(?User $user, ?int $universityId = null): array
    {
        $institution = $this->runtime->layoutRequirements($universityId);
        if ($user && $user->isLecturer()) {
            $prefs = \App\Models\LecturerLayoutPreference::where('user_id', $user->id)->first();
            if ($prefs) {
                foreach (array_keys($institution) as $key) {
                    if ($prefs->$key !== null && $prefs->$key !== false && $prefs->$key !== '') $institution[$key] = $prefs->$key;
                }
            }
        }
        return array_filter($institution, fn ($v) => $v !== null && $v !== '' && $v !== false);
    }

    protected function analyticsContext(string $feature, AiMode $mode, AiResponse $response, ?User $user, bool $cached): array
    {
        $source = $response->source;
        $isRule = in_array($source, ['rule_engine', 'rule_engine_fallback'], true);
        return [
            'request_id' => $response->requestId,
            'user_id' => $user?->id,
            'university_id' => $user?->university_id,
            'department_id' => $user?->department_id,
            'feature' => $feature,
            'mode' => $mode->value,
            'source' => $source,
            'provider' => $response->provider,
            'model' => $response->model,
            'fallback_used' => $response->fallbackUsed,
            'fallback_provider' => $response->fallbackProvider,
            'error_type' => $response->errorCode,
            'grounding_used' => $feature === 'knowledge_companion' || ! empty($response->metadata['grounding']),
            'metadata' => $response->metadata,
            'cached' => $cached,
            'success' => $response->success,
            'processing_time' => $response->processingTime,
            'cost' => $response->cost,
            'estimated_savings' => $isRule ? max(0.0, (float) ($response->cost ?? 0)) : 0,
            'score' => $response->score,
            'issue_count' => count($response->issues),
        ];
    }

    private function resolveRequestId(array $payload): string
    {
        $payloadId = trim((string) ($payload['_ai_request_id'] ?? ''));
        if ($payloadId !== '') return $payloadId;

        try {
            if (app()->bound('request')) {
                return UserFacingError::requestId(request());
            }
        } catch (\Throwable) {
            // CLI/unit-test execution has no HTTP request; use a local ID.
        }

        return (string) Str::uuid();
    }

    private function requestStartedAt(): float
    {
        try {
            if (app()->bound('request')) {
                return (float) request()->attributes->get('request_started_at', microtime(true));
            }
        } catch (\Throwable) {
        }
        return microtime(true);
    }

    private function lifecycleBudgetSeconds(): int
    {
        $configured = max(5, (int) config('ai.lifecycle_budget_seconds', 24));
        $phpLimit = (int) ini_get('max_execution_time');
        if ($phpLimit > 0) {
            // Keep a small margin for Laravel serialization/error handling.
            return max(5, min($configured, max(5, $phpLimit - 3)));
        }
        return $configured;
    }

    private function postProviderReserveSeconds(): int
    {
        return max(1, min(15, (int) config('ai.post_provider_reserve_seconds', 4)));
    }

    private function remainingBudgetSeconds(array $payload): float
    {
        $deadline = (float) ($payload['_ai_deadline_at'] ?? 0);
        return $deadline > 0 ? max(0.0, $deadline - microtime(true)) : (float) $this->lifecycleBudgetSeconds();
    }

    private function safeReleaseReservation(?User $user, array $reservation, string $requestId, string $feature): void
    {
        try {
            $this->billing->release($user, $reservation);
        } catch (\Throwable $e) {
            $this->safeMarkReconciliationRequired($requestId, $feature, 'release_failed');
            Log::channel('ai_provider')->warning('AI billing reservation release failed; reconciliation is required.', [
                'request_id' => $requestId,
                'feature' => $feature,
                'error_type' => $e::class,
            ]);
        }
    }

    private function safeMarkReconciliationRequired(string $requestId, string $feature, string $reason): void
    {
        try {
            $this->billing->markReconciliationRequired($requestId, $reason);
        } catch (\Throwable $e) {
            Log::channel('ai_provider')->warning('AI billing reconciliation marker failed; reserved funds remain protected for manual reconciliation.', [
                'request_id' => $requestId,
                'feature' => $feature,
                'reason' => $reason,
                'error_type' => $e::class,
            ]);
        }
    }

    private function lifecycleLog(string $level, string $message, string $requestId, string $feature, float $startedAt, array $context = []): void
    {
        try {
            Log::channel('ai_provider')->{$level}($message, array_merge([
                'request_id' => $requestId,
                'feature' => $feature,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $context));
        } catch (\Throwable) {
            // Observability must never block an otherwise valid AI response.
        }
    }

    private function recordAndReturn(AiResponse $response, string $feature, AiMode $mode, ?User $user, bool $cached, bool $account = false): AiResponse
    {
        if ($account) {
            try { $this->accountRequest($user, $response); } catch (\Throwable) {}
        }
        try {
            $this->analytics->record($this->analyticsContext($feature, $mode, $response, $user, $cached));
        } catch (\Throwable $e) {
            try {
                Log::channel('ai_provider')->warning('AI analytics failed after response; answer preserved.', [
                    'request_id' => $response->requestId,
                    'feature' => $feature,
                    'error_type' => $e::class,
                ]);
            } catch (\Throwable) {}
        }
        return $response;
    }
}
