<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiMode;
use App\Enums\AiProviderName;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Authoritative runtime AI configuration service.
 *
 * Priority:
 *  1. feature-specific institution/global SettingService override
 *  2. global/institution AI settings
 *  3. config/ai.php environment-backed bootstrap defaults
 *  4. application defaults
 *
 * Runtime provider selection must never be read directly from env/config by
 * feature modules. Environment values are installation/bootstrap fallbacks and
 * secret storage only.
 */
class AiRuntimeConfigService
{
    public const GLOBAL_PROVIDER = 'global';
    public const NO_PROVIDER = 'none';

    public function mode(?int $universityId = null): AiMode
    {
        $value = (string) $this->setting('ai_mode', config('ai.default_mode', 'rule_based'), $universityId);

        return AiMode::tryFrom($value) ?? AiMode::RULE_BASED;
    }

    public function defaultProvider(?int $universityId = null): string
    {
        return $this->normalizeProvider((string) $this->setting(
            'ai_default_provider',
            config('ai.default_provider', 'rule_based'),
            $universityId
        ));
    }

    public function defaultModel(?int $universityId = null): string
    {
        $provider = $this->defaultProvider($universityId);
        $value = trim((string) $this->setting('ai_default_model', config('ai.default_model', ''), $universityId));

        return $value !== '' ? $value : $this->providerModel($provider, $universityId);
    }

    public function fallbackProvider(?int $universityId = null): ?string
    {
        $value = trim((string) $this->setting('ai_fallback_provider', config('ai.fallback_provider', ''), $universityId));
        if ($value === '' || $value === self::NO_PROVIDER) {
            return null;
        }

        return $this->normalizeProvider($value);
    }

    public function fallbackModel(?int $universityId = null): ?string
    {
        $provider = $this->fallbackProvider($universityId);
        if (! $provider) return null;

        $value = trim((string) $this->setting('ai_fallback_model', config('ai.fallback_model', ''), $universityId));
        return $value !== '' ? $value : $this->providerModel($provider, $universityId);
    }

    public function secondaryFallbackProvider(?int $universityId = null): ?string
    {
        $value = trim((string) $this->setting(
            'ai_secondary_fallback_provider',
            config('ai.secondary_fallback_provider', ''),
            $universityId
        ));
        if ($value === '' || $value === self::NO_PROVIDER) return null;

        return $this->normalizeProvider($value);
    }

    public function secondaryFallbackModel(?int $universityId = null): ?string
    {
        $provider = $this->secondaryFallbackProvider($universityId);
        if (! $provider) return null;

        $value = trim((string) $this->setting('ai_secondary_fallback_model', config('ai.secondary_fallback_model', ''), $universityId));
        return $value !== '' ? $value : $this->providerModel($provider, $universityId);
    }

    public function automaticFailover(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_automatic_failover', true, $universityId);
    }

    public function providerHealthChecking(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_provider_health_enabled', true, $universityId);
    }

    public function requestTimeout(?int $universityId = null): int
    {
        return max(1, min(300, (int) $this->setting('ai_request_timeout', config('ai.request_timeout', 30), $universityId)));
    }

    public function retryCount(?int $universityId = null): int
    {
        return max(0, min(5, (int) $this->setting('ai_retry_count', config('ai.retry_count', 1), $universityId)));
    }

    public function retryDelayMs(?int $universityId = null): int
    {
        return max(0, min(10000, (int) $this->setting('ai_retry_delay_ms', config('ai.retry_delay_ms', 300), $universityId)));
    }

    public function globalTemperature(?int $universityId = null): float
    {
        return max(0.0, min(2.0, (float) $this->setting('ai_temperature', config('ai.temperature', 0.2), $universityId)));
    }

    public function maxTokens(?int $universityId = null): int
    {
        return max(1, min(128000, (int) $this->setting('ai_max_tokens', config('ai.max_tokens', 2048), $universityId)));
    }

    public function contextLimit(?int $universityId = null): int
    {
        return max(1000, (int) $this->setting('ai_context_limit', config('ai.context_limit', 16000), $universityId));
    }

    public function cacheEnabled(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_enable_cache', config('ai.enable_cache', true), $universityId);
    }

    public function cacheTtl(?int $universityId = null): int
    {
        return max(60, min(2592000, (int) $this->setting('ai_cache_ttl', config('ai.cache_ttl', 86400), $universityId)));
    }

    public function hybridEscalateWhenClean(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_hybrid_escalate_when_clean', config('ai.hybrid_escalate_when_clean', false), $universityId);
    }

    public function dailyRequestLimit(?int $universityId = null): int
    {
        return max(0, (int) $this->setting('ai_daily_request_limit', config('ai.daily_request_limit', 1000), $universityId));
    }

    public function monthlyRequestLimit(?int $universityId = null): int
    {
        return max(0, (int) $this->setting('ai_monthly_request_limit', config('ai.monthly_request_limit', 30000), $universityId));
    }

    public function maxMonthlyCost(?int $universityId = null): float
    {
        return max(0.0, (float) $this->setting('ai_max_cost', config('ai.max_cost', 100.0), $universityId));
    }

    public function rateLimitPerMinute(?int $universityId = null): int
    {
        return max(1, min(1000, (int) $this->setting('ai_rate_limit_per_minute', config('ai.rate_limit_per_minute', 20), $universityId)));
    }

    public function similarityThreshold(?int $universityId = null): int
    {
        return max(0, min(100, (int) $this->setting('ai_similarity_threshold', config('ai.similarity_threshold', 20), $universityId)));
    }

    /** @return array<string,mixed> */
    public function layoutRequirements(?int $universityId = null): array
    {
        $defaults = (array) config('ai.layout_requirements', []);
        $fonts = $this->setting('ai_layout_required_fonts', $defaults['required_fonts'] ?? [], $universityId);
        if (is_string($fonts)) {
            $decoded = json_decode($fonts, true);
            $fonts = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $fonts, -1, PREG_SPLIT_NO_EMPTY);
        }

        return array_filter([
            'required_fonts' => is_array($fonts) ? array_values(array_filter(array_map('strval', $fonts))) : [],
            'page_size' => $this->setting('ai_layout_page_size', $defaults['page_size'] ?? 'A4', $universityId),
            'min_margin_inches' => (float) $this->setting('ai_layout_min_margin_inches', $defaults['min_margin_inches'] ?? 1.0, $universityId),
            'line_spacing' => $this->setting('ai_layout_line_spacing', $defaults['line_spacing'] ?? '1.5', $universityId),
            'min_font_size_pt' => (int) $this->setting('ai_layout_min_font_size', $defaults['min_font_size_pt'] ?? 10, $universityId),
            'require_page_numbering' => (bool) $this->setting('ai_layout_require_page_numbering', $defaults['require_page_numbering'] ?? false, $universityId),
            'require_institution_branding' => (bool) $this->setting('ai_layout_require_branding', $defaults['require_institution_branding'] ?? false, $universityId),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function loggingEnabled(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_enable_logging', config('ai.enable_logging', true), $universityId);
    }

    public function groundingEnabled(?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_grounding_enabled', true, $universityId);
    }

    public function webResearchEnabled(?int $universityId = null): bool
    {
        // Current AcadFlow does not contain a live external web-search provider.
        // This flag remains authoritative and false by default so features cannot
        // silently treat an LLM provider as a web search engine.
        return (bool) $this->setting('ai_web_research_enabled', false, $universityId);
    }

    public function globalSystemPrompt(?int $universityId = null): string
    {
        return trim((string) $this->setting(
            'ai_global_system_prompt',
            'You are AcadFlow AI Academic Assistant. Respect authorization, tenant boundaries, academic integrity, source grounding, and uncertainty.',
            $universityId
        ));
    }

    public function featureEnabled(string $feature, ?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_feature_'.$feature, true, $universityId);
    }

    public function featureProvider(string $feature, ?int $universityId = null): string
    {
        $value = trim((string) $this->setting('ai_feature_'.$feature.'_provider', self::GLOBAL_PROVIDER, $universityId));
        if ($value === '' || $value === self::GLOBAL_PROVIDER) return self::GLOBAL_PROVIDER;

        return $this->normalizeProvider($value);
    }

    public function featureModel(string $feature, ?int $universityId = null): string
    {
        $value = trim((string) $this->setting('ai_feature_'.$feature.'_model', self::GLOBAL_PROVIDER, $universityId));
        return $value === '' ? self::GLOBAL_PROVIDER : $value;
    }

    public function featureRuleFallbackEnabled(string $feature, ?int $universityId = null): bool
    {
        return (bool) $this->setting('ai_feature_'.$feature.'_rule_fallback', true, $universityId);
    }

    /**
     * Provider/model selected for a feature before provider-health/fallback checks.
     *
     * @return array{provider:string,model:string,uses_global:bool}
     */
    public function featurePrimary(string $feature, ?int $universityId = null): array
    {
        $overrideProvider = $this->featureProvider($feature, $universityId);
        if ($overrideProvider === self::GLOBAL_PROVIDER) {
            return [
                'provider' => $this->defaultProvider($universityId),
                'model' => $this->defaultModel($universityId),
                'uses_global' => true,
            ];
        }

        $modelOverride = $this->featureModel($feature, $universityId);
        return [
            'provider' => $overrideProvider,
            'model' => $modelOverride === self::GLOBAL_PROVIDER
                ? $this->providerModel($overrideProvider, $universityId)
                : $modelOverride,
            'uses_global' => false,
        ];
    }

    public function providerEnabled(string $provider, ?int $universityId = null): bool
    {
        if ($provider === AiProviderName::RULE_BASED->value) return true;

        $bootstrap = $this->bootstrapProviderConfig($provider);
        $default = $this->bootstrapCredentialsPresent($provider, $bootstrap);
        return (bool) $this->platformSetting('ai_provider_'.$provider.'_enabled', $default);
    }

    public function providerModel(string $provider, ?int $universityId = null): string
    {
        if ($provider === AiProviderName::RULE_BASED->value) return 'rule-engine';

        $bootstrap = $this->bootstrapProviderConfig($provider);
        $model = trim((string) $this->platformSetting(
            'ai_provider_'.$provider.'_model',
            (string) ($bootstrap['model'] ?? '')
        ));

        return $this->supportedProviderModel($provider, $model);
    }

    /** @return list<string> */
    public function providerModels(string $provider, ?int $universityId = null): array
    {
        if ($provider === AiProviderName::RULE_BASED->value) return ['rule-engine'];

        $fallback = array_values(array_filter([$this->providerModel($provider, $universityId)]));
        $raw = $this->platformSetting('ai_provider_'.$provider.'_models', $fallback);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        }

        $models = array_values(array_unique(array_filter(array_map(
            fn ($model) => $this->supportedProviderModel($provider, trim((string) $model)),
            is_array($raw) ? $raw : $fallback
        ))));

        $current = $this->providerModel($provider, $universityId);
        if ($current !== '' && ! in_array($current, $models, true)) $models[] = $current;

        return $models;
    }

    public function providerConfigurationComplete(string $provider, ?int $universityId = null): bool
    {
        if ($provider === AiProviderName::RULE_BASED->value) return true;
        if (! $this->providerEnabled($provider, $universityId)) return false;

        $config = $this->providerConfig($provider, $universityId);
        if ($provider === AiProviderName::OLLAMA->value) {
            return trim((string) ($config['endpoint'] ?? $config['base_url'] ?? '')) !== ''
                && trim((string) ($config['model'] ?? '')) !== '';
        }
        if ($provider === AiProviderName::AZURE_OPENAI->value) {
            return trim((string) ($config['api_key'] ?? '')) !== ''
                && trim((string) ($config['endpoint'] ?? '')) !== ''
                && trim((string) ($config['model'] ?? '')) !== '';
        }

        return trim((string) ($config['api_key'] ?? '')) !== ''
            && trim((string) ($config['model'] ?? '')) !== '';
    }

    /**
     * Runtime provider adapter configuration. No feature should build this itself.
     *
     * @return array<string,mixed>
     */
    public function providerConfig(string $provider, ?int $universityId = null, ?string $modelOverride = null): array
    {
        $bootstrap = $this->bootstrapProviderConfig($provider);
        $model = trim((string) ($modelOverride ?: $this->providerModel($provider, $universityId)));
        $baseUrl = trim((string) $this->platformSetting(
            'ai_provider_'.$provider.'_base_url',
            (string) ($bootstrap['base_url'] ?? $bootstrap['endpoint'] ?? '')
        ));
        $temperature = (float) $this->platformSetting(
            'ai_provider_'.$provider.'_temperature',
            $bootstrap['temperature'] ?? $this->globalTemperature($universityId)
        );

        $config = array_merge($bootstrap, [
            'enabled' => $this->providerEnabled($provider, $universityId),
            'model' => $model,
            'models' => $this->providerModels($provider, $universityId),
            'temperature' => max(0.0, min(2.0, $temperature)),
            'request_timeout' => $this->requestTimeout($universityId),
            'retry_count' => $this->retryCount($universityId),
            'retry_delay_ms' => $this->retryDelayMs($universityId),
            'max_tokens' => $this->maxTokens($universityId),
            'context_limit' => $this->contextLimit($universityId),
            'connect_timeout' => max(1, min(60, (int) config('ai.http.connect_timeout', 10))),
            'ca_bundle' => trim((string) config('ai.http.ca_bundle', '')),
            'proxy' => trim((string) config('ai.http.proxy', '')),
            'force_ipv4' => (bool) config('ai.http.force_ipv4', false),
            'verify_tls' => (bool) config('ai.http.verify_tls', true),
        ]);

        if ($baseUrl !== '') {
            if ($provider === AiProviderName::AZURE_OPENAI->value || $provider === AiProviderName::OLLAMA->value) {
                $config['endpoint'] = $baseUrl;
            } else {
                $config['base_url'] = $baseUrl;
            }
        }

        $storedSecret = $this->providerSecret($provider, $universityId);
        if ($storedSecret !== null && $storedSecret !== '') {
            $config['api_key'] = $storedSecret;
        }

        return $config;
    }

    /**
     * Chain is strict: feature/global primary -> fallback -> secondary fallback.
     * `ai_provider_priority` is intentionally not used because it previously
     * allowed the configured default provider to be bypassed.
     *
     * @return list<array{provider:string,model:string,role:string}>
     */
    public function providerChain(string $feature, ?int $universityId = null): array
    {
        $primary = $this->featurePrimary($feature, $universityId);
        $chain = [[
            'provider' => $primary['provider'],
            'model' => $primary['model'],
            'role' => 'primary',
        ]];

        if ($this->automaticFailover($universityId)) {
            $fallback = $this->fallbackProvider($universityId);
            if ($fallback) {
                $chain[] = [
                    'provider' => $fallback,
                    'model' => $this->fallbackModel($universityId) ?: $this->providerModel($fallback, $universityId),
                    'role' => 'fallback',
                ];
            }
            $secondary = $this->secondaryFallbackProvider($universityId);
            if ($secondary) {
                $chain[] = [
                    'provider' => $secondary,
                    'model' => $this->secondaryFallbackModel($universityId) ?: $this->providerModel($secondary, $universityId),
                    'role' => 'secondary_fallback',
                ];
            }
        }

        $seen = [];
        return array_values(array_filter($chain, function (array $entry) use (&$seen): bool {
            $provider = $entry['provider'];
            if ($provider === '' || $provider === AiProviderName::RULE_BASED->value || isset($seen[$provider])) return false;
            $seen[$provider] = true;
            return true;
        }));
    }

    public function routingFingerprint(string $feature, ?int $universityId = null): string
    {
        return hash('sha256', json_encode([
            'generation' => (int) Cache::get('ai:runtime-config-generation', 1),
            'mode' => $this->mode($universityId)->value,
            'feature' => $feature,
            'feature_enabled' => $this->featureEnabled($feature, $universityId),
            'chain' => $this->providerChain($feature, $universityId),
            'rule_fallback' => $this->featureRuleFallbackEnabled($feature, $universityId),
            'grounding' => $this->groundingEnabled($universityId),
            'temperature' => $this->globalTemperature($universityId),
            'max_tokens' => $this->maxTokens($universityId),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function invalidate(): void
    {
        $key = 'ai:runtime-config-generation';
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
        foreach (AiProviderName::cases() as $provider) {
            if ($provider === AiProviderName::RULE_BASED) continue;
            Cache::forget('ai:provider-health:global:'.$provider->value);
        }
    }

    public function encryptProviderSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    private function providerSecret(string $provider, ?int $universityId = null): ?string
    {
        $stored = trim((string) $this->platformSetting('ai_provider_'.$provider.'_api_key', ''));
        if ($stored === '') return null;

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            // Backward compatibility for any legacy unencrypted provider secret.
            // The next explicit admin save will encrypt it.
            return $stored;
        }
    }

    private function setting(string $key, mixed $default, ?int $universityId): mixed
    {
        return $universityId !== null
            ? SettingService::get($key, $default, $universityId)
            : SettingService::getGlobal($key, $default);
    }

    /**
     * Provider credentials/endpoints/model registries are platform-owned.
     * Institution overrides control routing, not secrets or provider protocol
     * configuration. This prevents stale tenant overrides or cross-tenant
     * credential configuration from becoming a second provider source of truth.
     */
    private function platformSetting(string $key, mixed $default): mixed
    {
        return SettingService::getGlobal($key, $default);
    }

    /** @return array<string,mixed> */
    private function bootstrapProviderConfig(string $provider): array
    {
        return (array) config('ai.providers.'.$provider, []);
    }

    private function bootstrapCredentialsPresent(string $provider, array $bootstrap): bool
    {
        if ($provider === AiProviderName::OLLAMA->value) {
            return trim((string) ($bootstrap['endpoint'] ?? '')) !== '';
        }
        if ($provider === AiProviderName::AZURE_OPENAI->value) {
            return trim((string) ($bootstrap['api_key'] ?? '')) !== '' && trim((string) ($bootstrap['endpoint'] ?? '')) !== '';
        }

        return trim((string) ($bootstrap['api_key'] ?? '')) !== '';
    }

    private function supportedProviderModel(string $provider, string $model): string
    {
        if ($model === '') return '';

        $replacement = data_get(config('ai.retired_model_replacements', []), $provider.'.'.$model);
        return is_string($replacement) && trim($replacement) !== '' ? trim($replacement) : $model;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = Str::lower(trim($provider));
        return in_array($provider, AiProviderName::values(), true) ? $provider : AiProviderName::RULE_BASED->value;
    }
}
