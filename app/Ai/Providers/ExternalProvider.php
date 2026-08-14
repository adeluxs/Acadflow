<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared base for all external (API-backed) AI providers.
 *
 * These providers are intentionally resilient: if configuration is missing or a
 * request fails, they fall back to the RuleEngine so the user never sees an
 * error. Provider-specific HTTP request bodies and response parsers are implemented;
 * the rule engine always provides a valid structured response when a provider is
 * disabled, unavailable, rate-limited, or returns an invalid response.
 */
abstract class ExternalProvider implements AiProviderInterface
{
    public function __construct(
        protected RuleEngine $engine,
        protected array $config = []
    ) {}

    /**
     * Provider key (matches AiProviderName value).
     */
    abstract public function name(): string;

    /**
     * Whether required configuration (e.g. API key) is present.
     */
    public function isAvailable(): bool
    {
        return $this->hasCredentials();
    }

    abstract protected function hasCredentials(): bool;

    abstract protected function endpoint(): ?string;

    /**
     * Build the provider-specific request payload.
     */
    abstract protected function buildPrompt(string $feature, array $payload): string;

    /**
     * Turn a raw provider response into a structured AiResponse.
     */
    abstract protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse;

    public function handle(string $feature, array $payload): AiResponse
    {
        $start = microtime(true);

        // Graceful degradation: never break the request lifecycle. Honor the
        // runtime (DB) external-AI toggle, not just config (audit B10).
        if (! $this->isAvailable() || ! (bool) SettingService::get('ai_enable_external_ai', config('ai.enable_external_ai', false), (int) ($payload['_tenant_university_id'] ?? 0) ?: null)) {
            $fallback = $this->engine->run($feature, $payload);

            return $fallback->withData(['note' => 'External provider unavailable; used rule engine.']);
        }

        try {
            $response = Http::timeout((int) config('ai.request_timeout', 30))
                ->withHeaders($this->headers())
                ->post($this->endpoint(), $this->body($feature, $payload));

            if (! $response->successful()) {
                throw new \RuntimeException('Provider returned '.$response->status());
            }

            $raw = $response->json();
            return $this->parseResponse($feature, $raw, round(microtime(true) - $start, 4), $this->estimateCost($raw));
        } catch (\Throwable $e) {
            Log::warning("AI provider {$this->name()} failed; falling back to rule engine.", [
                'error' => $e->getMessage(),
            ]);

            $fallback = $this->engine->run($feature, $payload);

            return $fallback->withData(['note' => 'Provider failed; rule engine fallback used.']);
        }
    }

    protected function headers(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'model' => $this->config['model'] ?? 'default',
            'temperature' => (float) ($this->config['temperature'] ?? 0.2),
            'max_tokens' => (int) config('ai.max_tokens', 2048),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($payload)],
                ['role' => 'user', 'content' => $this->userPrompt($feature, $payload)],
            ],
        ];
    }

    protected function systemPrompt(array $payload): string
    {
        return (string) data_get($payload, '_prompt.system_prompt', 'You are AcadFlow AI Academic Assistant. Respond only with valid JSON.');
    }

    protected function userPrompt(string $feature, array $payload): string
    {
        return (string) data_get($payload, '_prompt.user_prompt', $this->buildPrompt($feature, $payload));
    }

    protected function estimateCost(array $raw): float
    {
        $input = (int) data_get($raw, 'usage.prompt_tokens', data_get($raw, 'usage.input_tokens', data_get($raw, 'usageMetadata.promptTokenCount', 0)));
        $output = (int) data_get($raw, 'usage.completion_tokens', data_get($raw, 'usage.output_tokens', data_get($raw, 'usageMetadata.candidatesTokenCount', 0)));
        $inputRate = (float) ($this->config['input_cost_per_million'] ?? 0);
        $outputRate = (float) ($this->config['output_cost_per_million'] ?? 0);
        return round((($input * $inputRate) + ($output * $outputRate)) / 1_000_000, 8);
    }
}
