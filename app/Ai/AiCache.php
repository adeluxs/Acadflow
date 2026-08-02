<?php

namespace App\Ai;

use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;

/**
 * Intelligent AI cache.
 *
 * Prevents unnecessary processing: if the same document/submission is analyzed
 * again without changes the cached analysis is returned. Cache is invalidated
 * when the document, submission, report, settings or rules change.
 */
class AiCache
{
    protected int $ttlSeconds;

    public function __construct()
    {
        $this->ttlSeconds = (int) config('ai.cache_ttl', 86400); // 24h
    }

    /**
     * Build a stable, deterministic cache key from the feature and a fingerprint
     * of the input (must be deterministic so repeated calls hit the same key).
     */
    protected function key(string $feature, array $payload, ?string $scope = null): string
    {
        $fingerprint = hash('sha256', json_encode([
            'feature' => $feature,
            'payload' => $payload,
        ]));

        return 'ai:'.($scope ? $scope.':' : '').$feature.':'.substr($fingerprint, 0, 32);
    }

    public function get(string $feature, array $payload, ?string $scope = null): mixed
    {
        if (! $this->enabled()) {
            return null;
        }

        return Cache::get($this->key($feature, $payload, $scope));
    }

    public function put(string $feature, array $payload, mixed $value, ?string $scope = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $key = $this->key($feature, $payload, $scope);

        Cache::put($key, $value, $this->ttlSeconds);

        // Track the key under its scope so forgetScope() can invalidate it
        // when the document/submission/report/settings/rules change (Phase 7).
        if ($scope) {
            $tag = "ai:scope:{$scope}";
            $keys = Cache::get($tag, []);
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
                Cache::put($tag, $keys, $this->ttlSeconds);
            }
        }
    }

    /**
     * Invalidate all cached AI results for a given scope (submission uuid, etc.).
     */
    public function forgetScope(string $scope): void
    {
        $tag = "ai:scope:{$scope}";
        $keys = Cache::get($tag, []);
        foreach ($keys as $k) {
            Cache::forget($k);
        }
        Cache::forget($tag);
    }

    /**
     * Invalidate all cached AI results for a given feature, regardless of scope.
     * Used when rule packs or AI settings change (Phase 7).
     */
    public function forgetFeature(string $feature): void
    {
        $prefix = "ai::{$feature}:";
        $store = Cache::getStore();

        // Best-effort scan: only repository stores expose an iterator.
        if (method_exists($store, 'iterator')) {
            foreach ($store->iterator() as $key => $value) {
                if (is_string($key) && str_starts_with($key, $prefix)) {
                    Cache::forget($key);
                }
            }
        }
    }

    protected function enabled(): bool
    {
        // Honor the runtime (DB) setting, falling back to config default
        // (audit B10).
        return (bool) SettingService::get('ai_enable_cache', config('ai.enable_cache', true));
    }
}
