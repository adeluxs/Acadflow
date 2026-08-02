<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Providers\AzureOpenAiProvider;
use App\Ai\Providers\ClaudeProvider;
use App\Ai\Providers\DeepSeekProvider;
use App\Ai\Providers\GeminiProvider;
use App\Ai\Providers\OllamaProvider;
use App\Ai\Providers\OpenAiProvider;
use App\Ai\Providers\RuleBasedProvider;
use App\Ai\Rules\RuleEngine;
use App\Enums\AiMode;
use App\Enums\AiProviderName;
use App\Services\SettingService;

/**
 * AI Router.
 *
 * Determines which engine (rule engine or external provider) should process a
 * request based on the configured AI mode. Mode is configurable through system
 * settings (ai_mode). Supports: rule_based, provider, hybrid, disabled.
 */
class AiRouter
{
    public function __construct(protected RuleEngine $engine) {}

    /**
     * Resolve the active provider for a given feature.
     *
     * Returns the provider or null when AI is disabled.
     */
    public function resolve(string $feature): ?AiProviderInterface
    {
        $mode = $this->mode();

        return match ($mode) {
            AiMode::DISABLED => null,
            AiMode::RULE_BASED => new RuleBasedProvider($this->engine),
            AiMode::PROVIDER => $this->provider($this->defaultProviderName()),
            AiMode::HYBRID => new RuleBasedProvider($this->engine), // manager runs hybrid flow
        };
    }

    public function mode(): AiMode
    {
        $value = SettingService::get('ai_mode', config('ai.default_mode', 'rule_based'));

        return AiMode::tryFrom($value) ?? AiMode::RULE_BASED;
    }

    public function defaultProviderName(): string
    {
        return (string) SettingService::get('ai_default_provider', config('ai.default_provider', 'rule_based'));
    }

    public function fallbackProviderName(): string
    {
        return (string) SettingService::get('ai_fallback_provider', config('ai.fallback_provider', 'rule_based'));
    }

    /**
     * Build a provider instance by name.
     */
    public function provider(string $name): AiProviderInterface
    {
        return match ($name) {
            AiProviderName::OPENAI->value => new OpenAiProvider($this->engine),
            AiProviderName::CLAUDE->value => new ClaudeProvider($this->engine),
            AiProviderName::GEMINI->value => new GeminiProvider($this->engine),
            AiProviderName::DEEPSEEK->value => new DeepSeekProvider($this->engine),
            AiProviderName::AZURE_OPENAI->value => new AzureOpenAiProvider($this->engine),
            AiProviderName::OLLAMA->value => new OllamaProvider($this->engine),
            default => new RuleBasedProvider($this->engine),
        };
    }

    public function availableProviders(): array
    {
        $names = array_merge(
            [AiProviderName::RULE_BASED->value],
            config('ai.provider_priority', [])
        );

        return array_values(array_unique($names));
    }
}
