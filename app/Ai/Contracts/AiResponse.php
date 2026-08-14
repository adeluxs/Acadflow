<?php

namespace App\Ai\Contracts;

use Illuminate\Support\Str;

/**
 * Provider-independent, evidence-rich response contract used by every AcadFlow
 * AI and rule-backed feature.
 */
final class AiResponse
{
    public function __construct(
        public string $source,
        public string $feature,
        public bool $success,
        public array $data = [],
        public ?string $summary = null,
        public ?float $score = null,
        public array $issues = [],
        public ?float $processingTime = null,
        public ?float $cost = null,
        public bool $cached = false,
        public ?string $requestId = null,
        public ?string $status = null,
        public array $findings = [],
        public ?string $severity = null,
        public array $evidence = [],
        public array $suggestedActions = [],
        public ?float $confidence = null,
        public bool $humanReviewRequired = false,
    ) {
        $this->requestId ??= (string) Str::uuid();
        $this->status ??= $success ? 'completed' : 'failed';
        $this->findings = $findings !== [] ? $findings : $issues;
        $this->severity ??= $this->highestSeverity($this->findings);
        $this->evidence = $evidence !== [] ? $evidence : $this->collectField($this->findings, 'evidence');
        $this->suggestedActions = $suggestedActions !== []
            ? $suggestedActions
            : $this->collectField($this->findings, 'suggestion');
        $this->confidence ??= $success ? ($source === 'rule_engine' ? 0.85 : 0.70) : 0.0;
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'status' => $this->status,
            'source' => $this->source,
            'provider' => $this->source,
            'feature' => $this->feature,
            'success' => $this->success,
            'summary' => $this->summary,
            'score' => $this->score,
            'findings' => $this->findings,
            'issues' => $this->issues,
            'severity' => $this->severity,
            'evidence' => $this->evidence,
            'suggested_actions' => $this->suggestedActions,
            'confidence' => $this->confidence,
            'human_review_required' => $this->humanReviewRequired,
            'data' => $this->data,
            'processing_time' => $this->processingTime,
            'cost' => $this->cost,
            'cached' => $this->cached,
        ];
    }

    public function withData(array $extra): self
    {
        $metaKeys = [
            'source', 'cost', 'status', 'findings', 'severity', 'evidence',
            'suggested_actions', 'confidence', 'human_review_required', 'request_id',
        ];

        return new self(
            source: $extra['source'] ?? $this->source,
            feature: $this->feature,
            success: $this->success,
            data: array_merge($this->data, array_diff_key($extra, array_flip($metaKeys))),
            summary: $this->summary,
            score: $this->score,
            issues: $this->issues,
            processingTime: $this->processingTime,
            cost: $extra['cost'] ?? $this->cost,
            cached: $this->cached,
            requestId: $extra['request_id'] ?? $this->requestId,
            status: $extra['status'] ?? $this->status,
            findings: $extra['findings'] ?? $this->findings,
            severity: $extra['severity'] ?? $this->severity,
            evidence: $extra['evidence'] ?? $this->evidence,
            suggestedActions: $extra['suggested_actions'] ?? $this->suggestedActions,
            confidence: isset($extra['confidence']) ? (float) $extra['confidence'] : $this->confidence,
            humanReviewRequired: (bool) ($extra['human_review_required'] ?? $this->humanReviewRequired),
        );
    }

    private function highestSeverity(array $findings): ?string
    {
        $rank = ['critical' => 4, 'high' => 3, 'warning' => 2, 'medium' => 2, 'info' => 1, 'low' => 1];
        $highest = null;
        $score = 0;
        foreach ($findings as $finding) {
            $severity = strtolower((string) ($finding['severity'] ?? ''));
            if (($rank[$severity] ?? 0) > $score) {
                $score = $rank[$severity];
                $highest = $severity;
            }
        }

        return $highest;
    }

    private function collectField(array $findings, string $field): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (array $finding) => $finding[$field] ?? null,
            array_filter($findings, 'is_array')
        ), static fn ($value) => is_string($value) && trim($value) !== '')));
    }
}
