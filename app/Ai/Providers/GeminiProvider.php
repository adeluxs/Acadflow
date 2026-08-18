<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;

class GeminiProvider extends ExternalProvider
{
    public function name(): string { return 'gemini'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        if ($base === '' || $this->model() === null) return null;
        if (str_contains(strtolower($base), ':generatecontent')) return $base;
        return $base.'/models/'.rawurlencode((string) $this->model()).':generateContent';
    }


    protected function headers(): array
    {
        return parent::headers() + [
            'x-goog-api-key' => (string) ($this->config['api_key'] ?? ''),
            'x-goog-api-client' => 'acadflow-laravel/1.0',
        ];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt($payload)]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $this->userPrompt($feature, $payload)]]]],
            'generationConfig' => [
                'temperature' => (float) ($this->config['temperature'] ?? 0.2),
                'maxOutputTokens' => $feature === '__health_check'
                    ? min(64, (int) ($this->config['max_tokens'] ?? 2048))
                    : (int) ($this->config['max_tokens'] ?? 2048),
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = (string) data_get($raw, 'candidates.0.content.parts.0.text', '');
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
