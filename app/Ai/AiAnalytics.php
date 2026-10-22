<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use App\Services\Ai\AiRuntimeConfigService;

/** AI request observability and aggregate usage analytics. */
class AiAnalytics
{
    public function __construct(private readonly AiRuntimeConfigService $runtime) {}

    public function record(array $data): void
    {
        $universityId = isset($data['university_id']) && $data['university_id'] ? (int) $data['university_id'] : null;
        if (! $this->runtime->loggingEnabled($universityId)) return;

        try {
            AiUsageLog::create([
                'request_id' => $data['request_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'university_id' => $data['university_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'feature' => $data['feature'] ?? 'unknown',
                'mode' => $data['mode'] ?? 'rule_based',
                'source' => $data['source'] ?? 'rule_engine',
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'fallback_used' => $data['fallback_used'] ?? false,
                'fallback_provider' => $data['fallback_provider'] ?? null,
                'error_type' => $data['error_type'] ?? null,
                'grounding_used' => $data['grounding_used'] ?? false,
                'metadata' => $this->sanitizeMetadata((array) ($data['metadata'] ?? [])),
                'cached' => $data['cached'] ?? false,
                'success' => $data['success'] ?? true,
                'processing_time' => $data['processing_time'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'estimated_savings' => $data['estimated_savings'] ?? 0,
                'score' => $data['score'] ?? null,
                'issue_count' => $data['issue_count'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function summary(?int $universityId = null, ?int $departmentId = null): array
    {
        $query = AiUsageLog::query();
        if ($universityId) $query->where('university_id', $universityId);
        if ($departmentId) $query->where('department_id', $departmentId);
        $guard=app('auth')->guard();
        $actor=method_exists($guard,'hasUser')&&$guard->hasUser()?$guard->user():null;
        if($actor?->isUniversityAdmin())$query->where(fn($q)=>$q->whereNull('department_id')->orWhereIn('department_id',app(\App\Services\AcademicOrganisationService::class)->departmentIds($actor)->select('departments.id')));

        $metrics = (clone $query)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) AS cache_hits')
            ->selectRaw("SUM(CASE WHEN source IN ('rule_engine', 'rule_engine_fallback') THEN 1 ELSE 0 END) AS rule_requests")
            ->selectRaw('SUM(CASE WHEN provider IS NOT NULL THEN 1 ELSE 0 END) AS provider_requests')
            ->selectRaw("SUM(CASE WHEN mode = 'hybrid' THEN 1 ELSE 0 END) AS hybrid_requests")
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS failures')
            ->selectRaw('SUM(CASE WHEN fallback_used = 1 THEN 1 ELSE 0 END) AS fallbacks')
            ->selectRaw('AVG(processing_time) AS avg_processing_time')
            ->selectRaw('COALESCE(SUM(cost), 0) AS total_cost')
            ->selectRaw('COALESCE(SUM(estimated_savings), 0) AS estimated_savings')
            ->first();

        $total = (int) ($metrics?->total ?? 0);
        $cacheHits = (int) ($metrics?->cache_hits ?? 0);
        $failures = (int) ($metrics?->failures ?? 0);

        $topFeatures = (clone $query)->selectRaw('feature, count(*) as total')
            ->groupBy('feature')->orderByDesc('total')->limit(5)->pluck('total', 'feature')->toArray();
        $topProviders = (clone $query)->whereNotNull('provider')->selectRaw('provider, count(*) as total')
            ->groupBy('provider')->orderByDesc('total')->limit(5)->pluck('total', 'provider')->toArray();

        return [
            'total_requests' => $total,
            'cache_hits' => $cacheHits,
            'cache_misses' => max(0, $total - $cacheHits),
            'rule_engine_requests' => (int) ($metrics?->rule_requests ?? 0),
            'provider_requests' => (int) ($metrics?->provider_requests ?? 0),
            'hybrid_requests' => (int) ($metrics?->hybrid_requests ?? 0),
            'fallback_requests' => (int) ($metrics?->fallbacks ?? 0),
            'failure_rate' => $total > 0 ? round($failures / $total, 4) : 0,
            'average_processing_time' => round((float) ($metrics?->avg_processing_time ?? 0), 4),
            'total_cost' => round((float) ($metrics?->total_cost ?? 0), 4),
            'estimated_savings' => round((float) ($metrics?->estimated_savings ?? 0), 4),
            'top_features' => $topFeatures,
            'top_providers' => $topProviders,
        ];
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $blocked = ['api_key', 'password', 'secret', 'authorization', 'token', 'credentials'];
        $walk = function (array $items) use (&$walk, $blocked): array {
            foreach ($items as $key => $value) {
                if (in_array(strtolower((string) $key), $blocked, true)) {
                    unset($items[$key]);
                    continue;
                }
                if (is_array($value)) $items[$key] = $walk($value);
                elseif (is_string($value) && strlen($value) > 2000) $items[$key] = substr($value, 0, 2000).'…';
            }
            return $items;
        };
        return $walk($metadata);
    }
}
