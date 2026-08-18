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
use Illuminate\Support\Facades\Cache;

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
    ) {}

    public function analyze(string $feature, array $payload, ?User $user = null, ?string $scope = null): AiResponse
    {
        return $this->analyzeRequest(new AiRequest($feature, $payload, $user, $scope));
    }

    public function analyzeRequest(AiRequest $request): AiResponse
    {
        $feature = $request->feature;
        $payload = $request->payload;
        $user = $request->user;
        $scope = $request->scope;
        $universityId = $request->universityId();
        $requestId = (string) \Illuminate\Support\Str::uuid();

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

        if ($feature === 'submission_validator') {
            $payload['layout_requirements'] = $this->resolveLayoutRequirements($user, $universityId);
        }

        $payload = $this->prompts->enrich($feature, $payload, $user);

        if ($cached = $this->cache->get($feature, $payload, $scope, $universityId)) {
            $cached->cached = true;
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
                : $this->callProviderChain($feature, $payload, $universityId, $requestId);
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
                $response = $this->runHybrid($feature, $payload, $ruleResponse, $universityId, $requestId);
            }
        }

        $response->cached = false;
        if ($response->success) {
            $this->cache->put($feature, $payload, $response, $scope, $universityId);
        }

        $this->accountRequest($user, (float) ($response->cost ?? 0.0));
        return $this->recordAndReturn($response, $feature, $mode, $user, false, false);
    }

    public function invalidateScope(string $scope): void { $this->cache->forgetScope($scope); }
    public function invalidateFeature(string $feature): void { $this->cache->forgetFeature($feature); }
    public function invalidateAll(): void { $this->cache->forgetAll(); }

    protected function runHybrid(string $feature, array $payload, AiResponse $ruleResponse, ?int $universityId, string $requestId): AiResponse
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
            $provider = $this->callProviderChain($feature, $payload, $universityId, $requestId);
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

    protected function callProviderChain(string $feature, array $payload, ?int $universityId, string $requestId): AiResponse
    {
        $chain = $this->router->providerChain($feature, $universityId);
        $route = $this->router->route($feature, $universityId);
        $attempts = [];
        $last = null;

        if ($chain === []) {
            return new AiResponse(
                source: 'unavailable', feature: $feature, success: false,
                summary: 'No enabled and configured external AI provider is selected for this feature.',
                requestId: $requestId, errorCode: 'AI_INVALID_CONFIGURATION', confidence: 0.0,
                metadata: ['routing' => $route, 'provider_attempts' => []],
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

                return $providerResponse->withData([
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
            }

            $last = $providerResponse;
        }

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
        $maxCost = $this->runtime->maxMonthlyCost($user?->university_id);
        if ($daily <= 0 && $monthly <= 0 && $maxCost <= 0) return false;

        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        return ($daily > 0 && (int) Cache::get("{$scope}:day:{$today}", 0) >= $daily)
            || ($monthly > 0 && (int) Cache::get("{$scope}:month:{$month}", 0) >= $monthly)
            || ($maxCost > 0 && (float) Cache::get("{$scope}:cost:{$month}", 0.0) >= $maxCost);
    }

    protected function accountRequest(?User $user, float $cost): void
    {
        $scope = $user ? "ai:limits:user:{$user->id}" : 'ai:limits:global';
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        Cache::increment("{$scope}:day:{$today}");
        Cache::increment("{$scope}:month:{$month}");
        $key = "{$scope}:cost:{$month}";
        Cache::put($key, round((float) Cache::get($key, 0.0) + max(0.0, $cost), 8), now()->addMonths(2));
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

    private function recordAndReturn(AiResponse $response, string $feature, AiMode $mode, ?User $user, bool $cached, bool $account = false): AiResponse
    {
        if ($account) $this->accountRequest($user, (float) ($response->cost ?? 0));
        $this->analytics->record($this->analyticsContext($feature, $mode, $response, $user, $cached));
        return $response;
    }
}
