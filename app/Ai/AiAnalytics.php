<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use App\Services\SettingService;

/**
 * Tracks every AI request for analytics (Phase 9).
 *
 * Metrics: rule vs provider vs hybrid requests, cache hits/misses, response
 * times, cost, estimated savings, usage per user/department/university, most
 * used features, failure rate, average processing time.
 */
class AiAnalytics
{
    public function record(array $data): void
    {
        if (! $this->loggingEnabled()) {
            return;
        }

        try {
            AiUsageLog::create([
                'user_id' => $data['user_id'] ?? null,
                'university_id' => $data['university_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'feature' => $data['feature'] ?? 'unknown',
                'mode' => $data['mode'] ?? 'rule_based',
                'source' => $data['source'] ?? 'rule_engine',
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

    /**
     * Aggregate metrics for dashboards.
     */
    public function summary(?int $universityId = null, ?int $departmentId = null): array
    {
        $query = AiUsageLog::query();

        if ($universityId) {
            $query->where('university_id', $universityId);
        }
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $total = (clone $query)->count();
        $cacheHits = (clone $query)->where('cached', true)->count();
        $ruleRequests = (clone $query)->whereIn('source', ['rule_engine', 'rule_engine_fallback'])->count();
        $providerRequests = (clone $query)->whereNotIn('source', ['rule_engine', 'rule_engine_fallback', 'cache', 'disabled'])->count();
        $hybridRequests = (clone $query)->where('mode', 'hybrid')->count();
        $failures = (clone $query)->where('success', false)->count();

        $topFeatures = (clone $query)
            ->selectRaw('feature, count(*) as total')
            ->groupBy('feature')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'feature')
            ->toArray();

        $avgTime = (clone $query)->average('processing_time') ?? 0;
        $totalCost = (clone $query)->sum('cost') ?? 0;
        $totalSavings = (clone $query)->sum('estimated_savings') ?? 0;

        return [
            'total_requests' => $total,
            'cache_hits' => $cacheHits,
            'cache_misses' => max(0, $total - $cacheHits),
            'rule_engine_requests' => $ruleRequests,
            'provider_requests' => $providerRequests,
            'hybrid_requests' => $hybridRequests,
            'failure_rate' => $total > 0 ? round($failures / $total, 4) : 0,
            'average_processing_time' => round((float) $avgTime, 4),
            'total_cost' => round((float) $totalCost, 4),
            'estimated_savings' => round((float) $totalSavings, 4),
            'top_features' => $topFeatures,
        ];
    }

    protected function loggingEnabled(): bool
    {
        return (bool) SettingService::get('ai_enable_logging', config('ai.enable_logging', true));
    }
}
