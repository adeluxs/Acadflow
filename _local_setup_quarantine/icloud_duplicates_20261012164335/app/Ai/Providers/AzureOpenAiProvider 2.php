<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiResponse;

class AzureOpenAiProvider extends ExternalProvider
{
    public function name(): string { return 'azure_openai'; }

    protected function hasCredentials(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== ''
            && trim((string) ($this->config['endpoint'] ?? '')) !== '';
    }

    protected function endpoint(): ?string
    {
        $endpoint = rtrim((string) ($this->config['endpoint'] ?? ''), '/');
        if ($endpoint === '' || $this->model() === null) return null;

        // Azure's current v1 endpoint can be configured directly as
        // https://<resource>.openai.azure.com/openai/v1. Existing AcadFlow
        // installations using deployment + api-version remain supported.
        if (preg_match('~/openai/v1$~i', $endpoint)) {
            return $endpoint.'/chat/completions';
        }

        $apiVersion = rawurlencode((string) ($this->config['api_version'] ?? '2024-10-21'));
        return $endpoint.'/openai/deployments/'.rawurlencode((string) $this->model()).'/chat/completions?api-version='.$apiVersion;
    }

    protected function headers(): array
    {
        return parent::headers() + ['api-key' => (string) ($this->config['api_key'] ?? '')];
    }

    protected function buildPrompt(string $feature, array $payload): string
    {
        return json_encode(['feature' => $feature, 'context' => $payload], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
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
