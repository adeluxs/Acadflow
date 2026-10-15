<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Enums\AiMode;
use App\Services\Ai\AiRuntimeConfigService;

/**
 * Central provider router. All runtime provider/model selection flows through
 * AiRuntimeConfigService and AiProviderRegistry.
 */
class AiRouter
{
    public function __construct(
        private readonly AiRuntimeConfigService $settings,
        private readonly AiProviderRegistry $providers,
    ) {}

    public function mode(?int $universityId = null): AiMode
    {
        return $this->settings->mode($universityId);
    }

    public function defaultProviderName(?int $universityId = null): string
    {
        return $this->settings->defaultProvider($universityId);
    }

    public function fallbackProviderName(?int $universityId = null): ?string
    {
        return $this->settings->fallbackProvider($universityId);
    }

    public function secondaryFallbackProviderName(?int $universityId = null): ?string
    {
        return $this->settings->secondaryFallbackProvider($universityId);
    }

    public function provider(string $name, ?int $universityId = null, ?string $model = null): AiProviderInterface
    {
        return $this->providers->make($name, $universityId, $model);
    }

    /** @return list<string> */
    public function requiredCapabilities(string $feature): array
    {
        return array_values((array) config('ai.feature_capabilities.'.$feature, []));
    }

    public function providerSupportsFeature(string $provider, string $feature): bool
    {
        return $this->providers->supports($provider, $this->requiredCapabilities($feature));
    }

    /** @return list<array{provider:string,model:string,role:string}> */
    public function providerChain(string $feature, ?int $universityId = null): array
    {
        return $this->settings->providerChain($feature, $universityId);
    }

    /** @return array<string,mixed> */
    public function route(string $feature, ?int $universityId = null): array
    {
        $primary = $this->settings->featurePrimary($feature, $universityId);
        return [
            'feature' => $feature,
            'mode' => $this->mode($universityId)->value,
            'feature_enabled' => $this->settings->featureEnabled($feature, $universityId),
            'requested_configuration' => $primary['uses_global'] ? 'global' : 'feature_override',
            'resolved_provider' => $primary['provider'],
            'resolved_model' => $primary['model'],
            'fallback_provider' => $this->settings->fallbackProvider($universityId),
            'fallback_model' => $this->settings->fallbackModel($universityId),
            'secondary_fallback_provider' => $this->settings->secondaryFallbackProvider($universityId),
            'secondary_fallback_model' => $this->settings->secondaryFallbackModel($universityId),
            'automatic_failover' => $this->settings->automaticFailover($universityId),
            'rule_fallback' => $this->settings->featureRuleFallbackEnabled($feature, $universityId),
            'provider_chain' => $this->providerChain($feature, $universityId),
            'required_capabilities' => $this->requiredCapabilities($feature),
            'provider_compatible' => $primary['provider'] === 'rule_based'
                ? $this->mode($universityId) === AiMode::RULE_BASED
                : $this->providerSupportsFeature($primary['provider'], $feature),
        ];
    }

    public function availableProviders(?int $universityId = null): array
    {
        return array_keys($this->providers->definitions($universityId));
    }
}
