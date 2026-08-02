<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * Azure OpenAI provider. Falls back to the rule engine on any failure.
 */
class AzureOpenAiProvider extends ExternalProvider
{
    public function __construct(RuleEngine $engine)
    {
        parent::__construct($engine, config('ai.providers.azure_openai', []));
    }

    public function name(): string
    {
        return 'azure_openai';
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->config['api_key'] ?? null) && ! empty($this->config['endpoint'] ?? null);
    }

    protected function endpoint(): ?string
    {
        $endpoint = rtrim($this->config['endpoint'] ?? '', '/');
        $model = $this->config['model'] ?? 'gpt-4o-mini';

        return "{$endpoint}/openai/deployments/{$model}/chat/completions?api-version=2024-02-15-preview";
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'api-key' => $this->config['api_key'],
        ];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload]);
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = $raw['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true) ?: ['raw' => $content];

        return new AiResponse(
            source: 'azure_openai',
            feature: $feature,
            success: true,
            data: $data['data'] ?? $data,
            summary: $data['summary'] ?? null,
            score: $data['score'] ?? null,
            issues: $data['issues'] ?? [],
            processingTime: $time,
            cost: $cost,
        );
    }
}
