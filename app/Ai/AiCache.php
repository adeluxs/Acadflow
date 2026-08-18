<?php

namespace App\Ai;

use App\Services\Ai\AiRuntimeConfigService;
use Illuminate\Support\Facades\Cache;

/**
 * Generation-based AI response cache.
 *
 * The AiManager includes an AI routing fingerprint in the payload, so changing
 * provider, model, mode or feature routing cannot return a stale response from
 * a previous configuration even before old entries expire.
 */
class AiCache
{
    public function __construct(private readonly AiRuntimeConfigService $runtime) {}

    protected function key(string $feature, array $payload, ?string $scope = null): string
    {
        $fingerprint = hash('sha256', json_encode([
            'feature' => $feature,
            'payload' => $payload,
            'global_generation' => $this->generation('global'),
            'feature_generation' => $this->generation('feature:'.$feature),
            'scope_generation' => $scope ? $this->generation('scope:'.$scope) : 0,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES));

        return 'ai:result:'.($scope ? hash('sha256', $scope).':' : '').$feature.':'.substr($fingerprint, 0, 40);
    }

    public function get(string $feature, array $payload, ?string $scope = null, ?int $universityId = null): mixed
    {
        return $this->runtime->cacheEnabled($universityId) ? Cache::get($this->key($feature, $payload, $scope)) : null;
    }

    public function put(string $feature, array $payload, mixed $value, ?string $scope = null, ?int $universityId = null): void
    {
        if ($this->runtime->cacheEnabled($universityId)) {
            Cache::put($this->key($feature, $payload, $scope), $value, $this->runtime->cacheTtl($universityId));
        }
    }

    public function forgetScope(string $scope): void { $this->bump('scope:'.$scope); }
    public function forgetFeature(string $feature): void { $this->bump('feature:'.$feature); }
    public function forgetAll(): void { $this->bump('global'); }

    protected function generation(string $name): int
    {
        return (int) Cache::get('ai:generation:'.hash('sha256', $name), 1);
    }

    protected function bump(string $name): void
    {
        $key = 'ai:generation:'.hash('sha256', $name);
        Cache::forever($key, $this->generation($name) + 1);
    }
}
