<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;

class OllamaProvider extends ExternalProvider
{
    public function name(): string { return 'ollama'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['endpoint'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $endpoint = rtrim((string) ($this->config['endpoint'] ?? 'http://localhost:11434'), '/');
        if ($endpoint === '') return null;
        return preg_match('~/api/chat$~i', $endpoint) ? $endpoint : $endpoint.'/api/chat';
    }


    protected function headers(): array
    {
        $headers = parent::headers();
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        if ($apiKey !== '') $headers['Authorization'] = 'Bearer '.$apiKey;
        return $headers;
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'model' => $this->model(),
            'stream' => false,
            'format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($payload)],
                ['role' => 'user', 'content' => $this->userPrompt($feature, $payload)],
            ],
            'options' => array_filter([
                'temperature' => (float) ($this->config['temperature'] ?? 0.2),
                'num_predict' => $feature === '__health_check' ? 64 : null,
            ], static fn ($value) => $value !== null),
        ];
    }

    protected function inputTokens(array $raw): int
    {
        return (int) ($raw['prompt_eval_count'] ?? 0);
    }

    protected function outputTokens(array $raw): int
    {
        return (int) ($raw['eval_count'] ?? 0);
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = (string) data_get($raw, 'message.content', '');
        $data = json_decode($content, true);
        if (! is_array($data)) $data = ['raw' => $content];

        return new AiResponse(
            source: $this->name(), feature: $feature, success: true,
            data: $data['data'] ?? $data, summary: $data['summary'] ?? null,
            score: isset($data['score']) ? (float) $data['score'] : null,
            issues: is_array($data['issues'] ?? null) ? $data['issues'] : [],
            processingTime: $time, cost: $cost, provider: $this->name(), model: $this->model(),
        );
    }
}
