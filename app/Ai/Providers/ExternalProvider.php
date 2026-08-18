<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Protocol-only base for external providers.
 *
 * All external provider traffic uses this transport so Test Connection and the
 * real AcadFlow AI runtime have identical timeout, TLS, proxy, DNS, logging and
 * error-classification behaviour. Provider adapters only define protocol data.
 *
 * IMPORTANT: this class deliberately has no RuleEngine dependency and never
 * silently converts a provider failure into a rule-based answer. Failover and
 * hybrid deterministic fallbacks are centralized in AiManager.
 */
abstract class ExternalProvider implements AiProviderInterface
{
    public function __construct(protected array $config = []) {}

    abstract public function name(): string;

    public function model(): ?string
    {
        $model = trim((string) ($this->config['model'] ?? ''));
        return $model !== '' ? $model : null;
    }

    public function capabilities(): array
    {
        return ['chat', 'structured_output'];
    }

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? true)
            && $this->hasCredentials()
            && $this->endpoint() !== null
            && $this->model() !== null;
    }

    abstract protected function hasCredentials(): bool;

    abstract protected function endpoint(): ?string;

    abstract protected function buildPrompt(string $feature, array $payload): string;

    abstract protected function parseResponse(string $feature, array $raw, float $time, float $cost): AiResponse;

    public function handle(string $feature, array $payload): AiResponse
    {
        if (! $this->isAvailable()) {
            $this->providerLog('warning', 'AI provider request skipped because configuration is incomplete.', [
                'feature' => $feature,
                'error_code' => 'AI_INVALID_CONFIGURATION',
            ]);
            return $this->failure($feature, 'AI_INVALID_CONFIGURATION', 'Provider configuration is incomplete or disabled.');
        }

        $start = microtime(true);
        $attempts = max(1, ((int) ($this->config['retry_count'] ?? 1)) + 1);
        $delayMs = max(0, (int) ($this->config['retry_delay_ms'] ?? 300));
        $last = null;
        $requestId = (string) Str::uuid();
        $configuredForceIpv4 = (bool) filter_var($this->config['force_ipv4'] ?? false, FILTER_VALIDATE_BOOL);
        $adaptiveIpv4 = $configuredForceIpv4;
        $ipv4FallbackAdded = false;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $endpoint = (string) $this->endpoint();
                $body = $this->body($feature, $payload);
                $this->providerLog('info', 'AI provider request started.', [
                    'request_id' => $requestId,
                    'feature' => $feature,
                    'attempt' => $attempt,
                    'endpoint' => $this->safeEndpoint($endpoint),
                    'ip_mode' => $adaptiveIpv4 ? 'ipv4' : 'automatic',
                ] + $this->safePayloadMetadata($body));

                // Send a raw JSON body instead of relying on implicit request
                // serialization. This guarantees provider-required fields such
                // as OpenAI's `model` parameter are present exactly as built.
                $response = $this->sendJson(
                    $this->httpClient($requestId, false, $adaptiveIpv4),
                    $endpoint,
                    $body
                );

                if ($response->successful()) {
                    $raw = (array) $response->json();
                    $elapsed = round(microtime(true) - $start, 4);
                    $parsed = $this->parseResponse($feature, $raw, $elapsed, $this->estimateCost($raw));
                    $providerRequestId = $this->providerRequestId($response);

                    $this->providerLog('info', 'AI provider request succeeded.', [
                        'request_id' => $requestId,
                        'provider_request_id' => $providerRequestId,
                        'feature' => $feature,
                        'attempt' => $attempt,
                        'http_status' => $response->status(),
                        'latency_ms' => (int) round($elapsed * 1000),
                    ]);

                    return $parsed->withData([
                        'request_id' => $requestId,
                        'provider' => $this->name(),
                        'model' => $this->model(),
                        'tokens_input' => $this->inputTokens($raw),
                        'tokens_output' => $this->outputTokens($raw),
                        'metadata' => array_filter([
                            'provider_attempts' => $attempt,
                            'provider_request_id' => $providerRequestId,
                        ]),
                    ]);
                }

                $last = $this->httpError($response);
                [$code, $message, $diagnostic] = $last;
                $this->providerLog('warning', 'AI provider rejected the request.', [
                    'request_id' => $requestId,
                    'feature' => $feature,
                    'attempt' => $attempt,
                    'http_status' => $response->status(),
                    'error_code' => $code,
                    'diagnostic' => $diagnostic,
                    'provider_request_id' => $this->providerRequestId($response),
                ]);

                if (! $this->retryableStatus($response->status()) || $attempt >= $attempts) {
                    break;
                }
            } catch (ConnectionException $e) {
                $last = $this->connectionError($e);
                [$code, $message, $diagnostic] = $last;
                $this->providerLog('error', 'AI provider network connection failed.', [
                    'request_id' => $requestId,
                    'feature' => $feature,
                    'attempt' => $attempt,
                    'error_code' => $code,
                    'diagnostic' => $diagnostic,
                    'ip_mode' => $adaptiveIpv4 ? 'ipv4' : 'automatic',
                ]);

                // Some Windows/local networks advertise IPv6 routes that cannot
                // actually reach particular AI providers. On the first retryable
                // network failure, add one IPv4-only transport attempt without
                // changing the administrator's configured provider or model.
                if (! $adaptiveIpv4 && ! $ipv4FallbackAdded && $this->shouldTryIpv4Fallback($code, $diagnostic)) {
                    $adaptiveIpv4 = true;
                    $ipv4FallbackAdded = true;
                    $attempts++;
                    $this->providerLog('warning', 'Retrying AI provider using IPv4 transport fallback.', [
                        'request_id' => $requestId,
                        'feature' => $feature,
                        'next_attempt' => $attempt + 1,
                        'error_code' => $code,
                    ]);
                    continue;
                }

                if ($attempt >= $attempts || ! $this->retryableConnectionCode($code)) break;
            } catch (\Throwable $e) {
                $last = ['AI_PROVIDER_UNAVAILABLE', 'Provider request failed.', $this->safeThrowableMessage($e)];
                $this->providerLog('error', 'Unexpected AI provider adapter failure.', [
                    'request_id' => $requestId,
                    'feature' => $feature,
                    'attempt' => $attempt,
                    'error_code' => 'AI_PROVIDER_UNAVAILABLE',
                    'error_type' => $e::class,
                    'diagnostic' => $this->safeThrowableMessage($e),
                ]);
                break;
            }

            if ($delayMs > 0) usleep($delayMs * 1000 * $attempt);
        }

        [$code, $message] = $last ?? ['AI_PROVIDER_UNAVAILABLE', 'Provider is unavailable.'];
        return $this->failure($feature, $code, $message, round(microtime(true) - $start, 4), $requestId);
    }

    public function healthCheck(): array
    {
        $start = microtime(true);
        $requestId = (string) Str::uuid();
        $base = [
            'provider' => $this->name(),
            'model' => $this->model(),
            'request_id' => $requestId,
            'checked_at' => now()->toIso8601String(),
            'log_file' => 'storage/logs/ai-provider.log',
        ];

        if (! $this->isAvailable()) {
            $result = $base + [
                'status' => 'configuration_incomplete',
                'message' => 'Provider credentials, endpoint, or model are incomplete.',
                'diagnostic' => 'Enable the provider and verify its API key, endpoint/base URL, and model in AI Settings.',
                'error_code' => 'AI_INVALID_CONFIGURATION',
            ];
            $this->providerLog('warning', 'AI provider health check skipped because configuration is incomplete.', $result);
            return $result;
        }

        try {
            $payload = [
                '_prompt' => [
                    'system_prompt' => 'You are an AcadFlow provider connectivity check. Return valid JSON only.',
                    'user_prompt' => 'Return {"status":"ok"}.',
                ],
            ];
            $endpoint = (string) $this->endpoint();
            $body = $this->body('__health_check', $payload);
            $configuredForceIpv4 = (bool) filter_var($this->config['force_ipv4'] ?? false, FILTER_VALIDATE_BOOL);
            $this->providerLog('info', 'AI provider health check started.', [
                'request_id' => $requestId,
                'endpoint' => $this->safeEndpoint($endpoint),
                'ip_mode' => $configuredForceIpv4 ? 'ipv4' : 'automatic',
            ] + $this->safePayloadMetadata($body));

            try {
                $response = $this->sendJson(
                    $this->httpClient($requestId, true, $configuredForceIpv4),
                    $endpoint,
                    $body
                );
            } catch (ConnectionException $firstConnectionError) {
                [$firstCode, , $firstDiagnostic] = $this->connectionError($firstConnectionError);
                if ($configuredForceIpv4 || ! $this->shouldTryIpv4Fallback($firstCode, $firstDiagnostic)) {
                    throw $firstConnectionError;
                }

                $this->providerLog('warning', 'AI provider health check retrying with IPv4 transport fallback.', [
                    'request_id' => $requestId,
                    'error_code' => $firstCode,
                    'diagnostic' => $firstDiagnostic,
                ]);
                $response = $this->sendJson(
                    $this->httpClient($requestId, true, true),
                    $endpoint,
                    $body
                );
            }

            $latency = (int) round((microtime(true) - $start) * 1000);
            if ($response->successful()) {
                $result = $base + [
                    'status' => 'healthy',
                    'message' => 'Connected successfully.',
                    'diagnostic' => 'The provider accepted an authenticated model request.',
                    'latency_ms' => $latency,
                    'provider_request_id' => $this->providerRequestId($response),
                ];
                $this->providerLog('info', 'AI provider health check succeeded.', $result);
                return $result;
            }

            [$code, $message, $diagnostic] = $this->httpError($response);
            $result = $base + [
                'status' => $this->healthStatus($code),
                'message' => $message,
                'diagnostic' => $diagnostic,
                'latency_ms' => $latency,
                'http_status' => $response->status(),
                'error_code' => $code,
                'provider_request_id' => $this->providerRequestId($response),
            ];
            $this->providerLog('warning', 'AI provider health check failed with HTTP response.', $result);
            return $result;
        } catch (ConnectionException $e) {
            [$code, $message, $diagnostic] = $this->connectionError($e);
            $result = $base + [
                'status' => $this->healthStatus($code),
                'message' => $message,
                'diagnostic' => $diagnostic,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error_code' => $code,
            ];
            $this->providerLog('error', 'AI provider health check could not connect.', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $base + [
                'status' => 'unavailable',
                'message' => 'Provider health check failed.',
                'diagnostic' => $this->safeThrowableMessage($e),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'error_code' => 'AI_PROVIDER_UNAVAILABLE',
            ];
            $this->providerLog('error', 'Unexpected AI provider health check failure.', $result + ['error_type' => $e::class]);
            return $result;
        }
    }

    /**
     * Send a provider request as an explicit raw JSON body. Laravel normally
     * serializes POST arrays as JSON, but using withBody here removes ambiguity
     * from provider/proxy integrations and lets diagnostics verify the exact
     * top-level payload structure without logging prompts or secrets.
     */
    protected function sendJson(PendingRequest $request, string $endpoint, array $body): Response
    {
        $json = json_encode(
            $body,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        return $request->withBody($json, 'application/json')->post($endpoint);
    }

    /** @return array<string,mixed> */
    protected function safePayloadMetadata(array $body): array
    {
        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        return array_filter([
            'payload_model' => isset($body['model']) && is_scalar($body['model']) ? (string) $body['model'] : null,
            'payload_keys' => array_values(array_map('strval', array_keys($body))),
            'payload_bytes' => strlen($encoded),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function shouldTryIpv4Fallback(string $code, string $diagnostic): bool
    {
        if (! in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_NETWORK_ERROR', 'AI_DNS_ERROR', 'AI_CONNECTION_REFUSED'], true)) {
            return false;
        }

        $diagnostic = strtolower($diagnostic);
        return str_contains($diagnostic, 'curl error 28')
            || str_contains($diagnostic, 'connection timed out')
            || str_contains($diagnostic, 'failed to connect')
            || str_contains($diagnostic, 'could not connect')
            || str_contains($diagnostic, 'resolve');
    }

    /**
     * Shared HTTP transport for Test Connection and real requests.
     */
    protected function httpClient(string $requestId, bool $healthCheck = false, ?bool $forceIpv4Override = null): PendingRequest
    {
        $requestTimeout = max(1, (int) ($this->config['request_timeout'] ?? 30));
        if ($healthCheck) $requestTimeout = min(30, max(5, $requestTimeout));
        $connectTimeout = max(1, min($requestTimeout, (int) ($this->config['connect_timeout'] ?? 10)));

        $options = [];
        $verifyTls = filter_var($this->config['verify_tls'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $verifyTls = $verifyTls ?? true;
        $caBundle = trim((string) ($this->config['ca_bundle'] ?? ''));
        if ($caBundle !== '' && is_file($caBundle)) {
            $options['verify'] = $caBundle;
        } else {
            $options['verify'] = $verifyTls;
        }

        $proxy = trim((string) ($this->config['proxy'] ?? ''));
        if ($proxy !== '') $options['proxy'] = $proxy;

        $configuredForceIpv4 = filter_var($this->config['force_ipv4'] ?? false, FILTER_VALIDATE_BOOL);
        $forceIpv4 = $forceIpv4Override ?? $configuredForceIpv4;
        if ($forceIpv4 && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $options['curl'] = [constant('CURLOPT_IPRESOLVE') => constant('CURL_IPRESOLVE_V4')];
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout($requestTimeout)
            ->connectTimeout($connectTimeout)
            ->withOptions($options)
            ->withUserAgent('AcadFlow/1.0 Laravel-AI')
            ->withHeaders($this->headers() + [
                'X-AcadFlow-Request-Id' => $requestId,
            ]);
    }

    protected function headers(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    protected function body(string $feature, array $payload): array
    {
        return [
            'model' => $this->model(),
            'temperature' => (float) ($this->config['temperature'] ?? 0.2),
            'max_tokens' => $feature === '__health_check'
                ? min(64, (int) ($this->config['max_tokens'] ?? 2048))
                : (int) ($this->config['max_tokens'] ?? 2048),
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
        $inputRate = (float) ($this->config['input_cost_per_million'] ?? 0);
        $outputRate = (float) ($this->config['output_cost_per_million'] ?? 0);
        return round((($this->inputTokens($raw) * $inputRate) + ($this->outputTokens($raw) * $outputRate)) / 1_000_000, 8);
    }

    protected function inputTokens(array $raw): int
    {
        return (int) data_get($raw, 'usage.prompt_tokens', data_get($raw, 'usage.input_tokens', data_get($raw, 'usageMetadata.promptTokenCount', 0)));
    }

    protected function outputTokens(array $raw): int
    {
        return (int) data_get($raw, 'usage.completion_tokens', data_get($raw, 'usage.output_tokens', data_get($raw, 'usageMetadata.candidatesTokenCount', 0)));
    }

    /** @return array{0:string,1:string,2:string} */
    protected function httpError(Response $response): array
    {
        $diagnostic = $this->providerErrorMessage($response);
        $status = $response->status();

        [$code, $message] = match (true) {
            in_array($status, [401, 403], true) => ['AI_PROVIDER_AUTH_FAILED', 'Provider authentication failed.'],
            $status === 404 => ['AI_MODEL_NOT_FOUND', 'Configured provider model or endpoint was not found.'],
            $status === 408 => ['AI_PROVIDER_TIMEOUT', 'Provider request timed out.'],
            $status === 429 => ['AI_PROVIDER_RATE_LIMITED', 'Provider rate limit was reached.'],
            $status >= 500 => ['AI_PROVIDER_UNAVAILABLE', 'Provider is temporarily unavailable.'],
            default => ['AI_INVALID_CONFIGURATION', 'Provider rejected the request.'],
        };

        return [$code, $message, $diagnostic !== '' ? $diagnostic : "HTTP {$status} returned by provider."];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function connectionError(ConnectionException $exception): array
    {
        $diagnostic = $this->safeThrowableMessage($exception);
        $lower = strtolower($diagnostic);

        if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl') || str_contains($lower, 'curl error 60')) {
            return ['AI_TLS_ERROR', 'Secure TLS connection to the provider failed.', $diagnostic];
        }
        if (str_contains($lower, 'could not resolve') || str_contains($lower, 'name resolution') || str_contains($lower, 'getaddrinfo')) {
            return ['AI_DNS_ERROR', 'The provider hostname could not be resolved.', $diagnostic];
        }
        if (str_contains($lower, 'connection refused') || str_contains($lower, 'failed to connect') || str_contains($lower, "couldn't connect") || str_contains($lower, 'could not connect')) {
            return ['AI_CONNECTION_REFUSED', 'A connection to the provider could not be established.', $diagnostic];
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($lower, 'curl error 28')) {
            return ['AI_PROVIDER_TIMEOUT', 'Provider request timed out.', $diagnostic];
        }

        return ['AI_NETWORK_ERROR', 'A network error prevented the provider request.', $diagnostic];
    }

    protected function retryableStatus(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    protected function retryableConnectionCode(string $code): bool
    {
        return in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_NETWORK_ERROR', 'AI_DNS_ERROR', 'AI_CONNECTION_REFUSED'], true);
    }

    protected function healthStatus(string $errorCode): string
    {
        return match ($errorCode) {
            'AI_PROVIDER_AUTH_FAILED' => 'authentication_failed',
            'AI_PROVIDER_RATE_LIMITED' => 'rate_limited',
            'AI_PROVIDER_TIMEOUT' => 'timeout',
            'AI_TLS_ERROR' => 'tls_error',
            'AI_DNS_ERROR' => 'dns_error',
            'AI_CONNECTION_REFUSED' => 'connection_refused',
            'AI_NETWORK_ERROR' => 'network_error',
            'AI_MODEL_NOT_FOUND' => 'model_unavailable',
            'AI_INVALID_CONFIGURATION' => 'configuration_incomplete',
            default => 'unavailable',
        };
    }

    protected function failure(string $feature, string $code, string $message, ?float $time = null, ?string $requestId = null): AiResponse
    {
        return new AiResponse(
            source: $this->name(),
            feature: $feature,
            success: false,
            summary: $message,
            processingTime: $time,
            requestId: $requestId,
            provider: $this->name(),
            model: $this->model(),
            errorCode: $code,
            confidence: 0.0,
        );
    }

    protected function providerRequestId(Response $response): ?string
    {
        foreach (['x-request-id', 'request-id', 'apim-request-id', 'x-goog-request-id'] as $header) {
            $value = trim((string) $response->header($header));
            if ($value !== '') return $value;
        }
        return null;
    }

    protected function providerErrorMessage(Response $response): string
    {
        $json = $response->json();
        $message = '';
        if (is_array($json)) {
            foreach (['error.message', 'message', 'error.detail', 'detail'] as $path) {
                $candidate = data_get($json, $path);
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    $message = trim((string) $candidate);
                    break;
                }
            }
            if ($message === '' && isset($json['error']) && is_string($json['error'])) {
                $message = trim($json['error']);
            }
        }
        if ($message === '') {
            $message = trim((string) $response->body());
        }
        return $this->sanitizeDiagnostic($message);
    }

    protected function safeThrowableMessage(\Throwable $exception): string
    {
        return $this->sanitizeDiagnostic($exception->getMessage());
    }

    protected function sanitizeDiagnostic(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';

        $secrets = array_filter([
            (string) ($this->config['api_key'] ?? ''),
            (string) ($this->config['token'] ?? ''),
            (string) ($this->config['password'] ?? ''),
        ], static fn (string $secret): bool => strlen($secret) >= 6);
        foreach ($secrets as $secret) $value = str_replace($secret, '[REDACTED]', $value);

        $value = preg_replace('/(authorization|api[-_ ]?key|x-api-key|x-goog-api-key)\s*[:=]\s*[^\s,;]+/i', '$1=[REDACTED]', $value) ?? $value;
        $value = preg_replace('/([?&](?:key|api_key|token)=)[^&\s]+/i', '$1[REDACTED]', $value) ?? $value;

        return Str::limit($value, 1800, '…');
    }

    protected function safeEndpoint(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if (! is_array($parts)) return '[invalid endpoint]';
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        return ($scheme !== '' && $host !== '') ? $scheme.'://'.$host.$port.$path : $path;
    }

    protected function providerLog(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'provider' => $this->name(),
            'model' => $this->model(),
        ], array_filter($context, static fn ($value) => $value !== null && $value !== ''));

        try {
            Log::channel('ai_provider')->{$level}($message, $context);
        } catch (\Throwable) {
            Log::{$level}($message, $context);
        }
    }
}
