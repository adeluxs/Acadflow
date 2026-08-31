<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Providers\AzureOpenAiProvider;
use App\Ai\Providers\ClaudeProvider;
use App\Ai\Providers\DeepSeekProvider;
use App\Ai\Providers\GeminiProvider;
use App\Ai\Providers\GrokProvider;
use App\Ai\Providers\OllamaProvider;
use App\Ai\Providers\OpenAiProvider;
use App\Ai\Providers\OpenRouterProvider;
use App\Ai\Providers\RuleBasedProvider;
use App\Ai\Rules\RuleEngine;
use App\Enums\AiProviderName;
use App\Services\Ai\AiRuntimeConfigService;
use Illuminate\Support\Facades\Cache;

/**
 * Single registry/factory for every provider supported by the current AcadFlow
 * codebase. Provider adapters contain protocol details only; feature/business
 * logic stays in AiManager and feature services.
 */
class AiProviderRegistry
{
    public function __construct(
        private readonly RuleEngine $engine,
        private readonly AiRuntimeConfigService $settings,
    ) {}

    /** @return array<string,array<string,mixed>> */
    public function definitions(?int $universityId = null): array
    {
        $definitions = [];
        foreach (AiProviderName::cases() as $provider) {
            $name = $provider->value;
            $definitions[$name] = [
                'name' => $name,
                'label' => $provider->label(),
                'enabled' => $this->settings->providerEnabled($name, $universityId),
                'configured' => $this->settings->providerConfigurationComplete($name, $universityId),
                'model' => $this->settings->providerModel($name, $universityId),
                'models' => $this->settings->providerModels($name, $universityId),
                'capabilities' => $this->capabilities($name),
                'requires_api_key' => ! in_array($name, [AiProviderName::RULE_BASED->value, AiProviderName::OLLAMA->value], true),
                'supports_base_url' => $name !== AiProviderName::RULE_BASED->value,
            ];
        }
        return $definitions;
    }

    public function make(string $provider, ?int $universityId = null, ?string $model = null): AiProviderInterface
    {
        if ($provider === AiProviderName::RULE_BASED->value) {
            return new RuleBasedProvider($this->engine);
        }

        return $this->makeWithConfig($provider, $this->settings->providerConfig($provider, $universityId, $model));
    }

    /**
     * Build an adapter from an already-normalized configuration. Used by the
     * Test Connection action so unsaved credentials can be tested without ever
     * persisting or exposing them.
     *
     * @param array<string,mixed> $config
     */
    public function makeWithConfig(string $provider, array $config): AiProviderInterface
    {
        return match ($provider) {
            AiProviderName::OPENAI->value => new OpenAiProvider($config),
            AiProviderName::CLAUDE->value => new ClaudeProvider($config),
            AiProviderName::GEMINI->value => new GeminiProvider($config),
            AiProviderName::DEEPSEEK->value => new DeepSeekProvider($config),
            AiProviderName::GROK->value => new GrokProvider($config),
            AiProviderName::OPENROUTER->value => new OpenRouterProvider($config),
            AiProviderName::AZURE_OPENAI->value => new AzureOpenAiProvider($config),
            AiProviderName::OLLAMA->value => new OllamaProvider($config),
            AiProviderName::RULE_BASED->value => new RuleBasedProvider($this->engine),
            default => throw new \InvalidArgumentException('Unsupported AI provider: '.$provider),
        };
    }

    /** @return list<string> */
    public function capabilities(string $provider): array
    {
        return match ($provider) {
            AiProviderName::RULE_BASED->value => ['rules', 'validation'],
            AiProviderName::OPENAI->value,
            AiProviderName::CLAUDE->value,
            AiProviderName::GEMINI->value,
            AiProviderName::DEEPSEEK->value,
            AiProviderName::GROK->value,
            AiProviderName::OPENROUTER->value,
            AiProviderName::AZURE_OPENAI->value,
            AiProviderName::OLLAMA->value => ['chat', 'structured_output'],
            default => [],
        };
    }

    public function supports(string $provider, array $requiredCapabilities): bool
    {
        return count(array_diff($requiredCapabilities, $this->capabilities($provider))) === 0;
    }

    /** @return array<string,mixed> */
    public function health(string $provider, ?int $universityId = null, bool $force = false): array
    {
        if ($provider === AiProviderName::RULE_BASED->value) {
            return ['status' => 'healthy', 'provider' => $provider, 'message' => 'Local rule engine is available.', 'checked_at' => now()->toIso8601String()];
        }

        if (! $this->settings->providerEnabled($provider, $universityId)) {
            return ['status' => 'disabled', 'provider' => $provider, 'message' => 'Provider is disabled in AI Settings.', 'checked_at' => now()->toIso8601String()];
        }

        if (! $this->settings->providerConfigurationComplete($provider, $universityId)) {
            return ['status' => 'configuration_incomplete', 'provider' => $provider, 'message' => 'Provider credentials, endpoint, or model are incomplete.', 'checked_at' => now()->toIso8601String()];
        }

        $key = 'ai:provider-health:global:'.$provider;
        if (! $force && $this->settings->providerHealthChecking($universityId)) {
            $cached = Cache::get($key);
            if (is_array($cached)) return $cached;
        }

        $result = $this->make($provider, $universityId)->healthCheck();
        if ($this->settings->providerHealthChecking($universityId)) {
            Cache::put($key, $result, now()->addMinutes(5));
        }

        return $result;
    }
    /** @param array<string,mixed> $config */
    public function healthWithConfig(string $provider, array $config): array
    {
        if ($provider === AiProviderName::RULE_BASED->value) {
            return ['status' => 'healthy', 'provider' => $provider, 'message' => 'Local rule engine is available.', 'checked_at' => now()->toIso8601String()];
        }

        return $this->makeWithConfig($provider, $config)->healthCheck();
    }

}
