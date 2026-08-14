<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * Ollama (self-hosted) provider. Falls back to the rule engine on any failure.
 */
class OllamaProvider extends ExternalProvider
{
    public function __construct(RuleEngine $engine)
    {
        parent::__construct($engine, config('ai.providers.ollama', []));
    }

    public function name(): string
    {
        return 'ollama';
    }

    protected function hasCredentials(): bool
    {
        return ! empty($this->config['endpoint'] ?? null);
    }

    protected function endpoint(): ?string
    {
        return rtrim($this->config['endpoint'] ?? 'http://localhost:11434', '/').'/api/chat';
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload]);
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'model' => $this->config['model'] ?? 'llama3',
            'stream' => false,
            'format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($payload)],
                ['role' => 'user', 'content' => $this->userPrompt($feature, $payload)],
            ],
            'options' => ['temperature' => (float) ($this->config['temperature'] ?? 0.2)],
        ];
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = $raw['message']['content'] ?? '';
        $data = json_decode($content, true) ?: ['raw' => $content];

        return new AiResponse(
            source: 'ollama',
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
