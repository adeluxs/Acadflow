<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * Anthropic Claude provider. Falls back to the rule engine on any failure.
 */
class ClaudeProvider extends ExternalProvider
{
    public function __construct(RuleEngine $engine)
    {
        parent::__construct($engine, config('ai.providers.claude', []));
    }

    public function name(): string
    {
        return 'claude';
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->config['api_key'] ?? null);
    }

    protected function endpoint(): ?string
    {
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => '2023-06-01',
        ];
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'model' => $this->config['model'] ?? 'claude-3-5-sonnet-latest',
            'max_tokens' => (int) config('ai.max_tokens', 2048),
            'temperature' => (float) ($this->config['temperature'] ?? 0.2),
            'system' => 'You are AcadFlow AI Academic Assistant. Respond only with JSON.',
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($feature, $payload)],
            ],
        ];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload]);
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = $raw['content'][0]['text'] ?? '';
        $data = json_decode($content, true) ?: ['raw' => $content];

        return new AiResponse(
            source: 'claude',
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
