<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * OpenAI provider (GPT models). Falls back to the rule engine on any failure.
 */
class OpenAiProvider extends ExternalProvider
{
    public function __construct(RuleEngine $engine)
    {
        parent::__construct($engine, config('ai.providers.openai', []));
    }

    public function name(): string
    {
        return 'openai';
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->config['api_key'] ?? null);
    }

    protected function endpoint(): ?string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->config['api_key'],
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
            source: 'openai',
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
