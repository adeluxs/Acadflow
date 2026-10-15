<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;

class ClaudeProvider extends ExternalProvider
{
    public function name(): string { return 'claude'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com/v1'), '/');
        if ($base === '') return null;
        return preg_match('~/messages$~i', $base) ? $base : $base.'/messages';
    }

    protected function headers(): array
    {
        return parent::headers() + [
            'x-api-key' => (string) ($this->config['api_key'] ?? ''),
            'anthropic-version' => (string) ($this->config['api_version'] ?? '2023-06-01'),
        ];
    }

    protected function body(string $feature, array $payload): array
    {
        $body = [
            'model' => $this->model(),
            'max_tokens' => $feature === '__health_check'
                ? min(64, (int) ($this->config['max_tokens'] ?? 2048))
                : (int) ($this->config['max_tokens'] ?? 2048),
            'system' => $this->systemPrompt($payload),
            'messages' => [['role' => 'user', 'content' => $this->userPrompt($feature, $payload)]],
        ];

        // Claude 4.7+ and newer families reject sampling parameters such
        // as temperature. Keep the central temperature setting only for models
        // whose Messages API still accepts it.
        if ($this->supportsTemperature()) {
            $body['temperature'] = (float) ($this->config['temperature'] ?? 0.2);
        }

        return $body;
    }

    private function supportsTemperature(): bool
    {
        $model = strtolower((string) $this->model());
        if ($model === '') return true;
        if (str_contains($model, 'mythos') || str_contains($model, 'fable')) return false;

        if (preg_match('/^claude-(?:opus|sonnet)-(\d+)(?:-(\d+))?/', $model, $matches)) {
            $major = (int) ($matches[1] ?? 0);
            $minor = (int) ($matches[2] ?? 0);
            if ($major >= 5) return false;
            if ($major === 4 && $minor >= 7) return false;
        }

        return true;
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse
    {
        $content = (string) data_get($raw, 'content.0.text', '');
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
