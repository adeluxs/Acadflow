<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * Google Gemini provider. Falls back to the rule engine on any failure.
 */
class GeminiProvider extends ExternalProvider
{
    public function __construct(RuleEngine $engine)
    {
        parent::__construct($engine, config('ai.providers.gemini', []));
    }

    public function name(): string
    {
        return 'gemini';
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->config['api_key'] ?? null);
    }

    protected function endpoint(): ?string
    {
        $model = $this->config['model'] ?? 'gemini-1.5-flash';

        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".$this->config['api_key'];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload]);
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt($payload)]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $this->userPrompt($feature, $payload)]]]],
            'generationConfig' => [
                'temperature' => (float) ($this->config['temperature'] ?? 0.2),
                'maxOutputTokens' => (int) config('ai.max_tokens', 2048),
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = $raw['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $data = json_decode($content, true) ?: ['raw' => $content];

        return new AiResponse(
            source: 'gemini',
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
