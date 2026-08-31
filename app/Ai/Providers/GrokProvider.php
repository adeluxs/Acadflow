<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;

/**
 * xAI Grok adapter.
 *
 * xAI exposes an OpenAI-compatible Chat Completions endpoint. This adapter is
 * intentionally protocol-only and inherits AcadFlow's shared HTTP transport,
 * diagnostics, retries, TLS/proxy settings and normalized error handling from
 * ExternalProvider.
 */
class GrokProvider extends ExternalProvider
{
    public function name(): string { return 'grok'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.x.ai/v1'), '/');
        if ($base === '') return null;

        return preg_match('~/chat/completions$~i', $base) ? $base : $base.'/chat/completions';
    }

    protected function headers(): array
    {
        return parent::headers() + [
            'Authorization' => 'Bearer '.(string) ($this->config['api_key'] ?? ''),
        ];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(
            ['feature' => $feature, 'context' => $payload],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '{}';
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = (string) data_get($raw, 'choices.0.message.content', '');
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
