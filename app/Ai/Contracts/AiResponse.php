<?php

namespace App\Ai\Contracts;

use Illuminate\Support\Str;

/**
 * Provider-independent response contract used by every AcadFlow AI feature.
 *
 * `source` describes how the answer was produced (provider, rule_engine,
 * rule_engine_fallback, disabled, etc.). `provider` and `model` record the
 * actual external provider/model when one handled the request.
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
        public ?string $provider = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public bool $fallbackUsed = false,
        public ?string $fallbackProvider = null,
        public ?string $errorCode = null,
        public array $metadata = [],
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

        if ($this->provider === null && ! in_array($source, ['rule_engine', 'rule_engine_fallback', 'disabled', 'limit_exceeded', 'unavailable'], true)) {
            $this->provider = $source;
        }
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'status' => $this->status,
            'source' => $this->source,
            'provider' => $this->provider,
            'model' => $this->model,
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
            'tokens_input' => $this->inputTokens,
            'tokens_output' => $this->outputTokens,
            'fallback_used' => $this->fallbackUsed,
            'fallback_provider' => $this->fallbackProvider,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }

    public function withData(array $extra): self
    {
        $metaKeys = [
            'source', 'cost', 'status', 'findings', 'severity', 'evidence',
            'suggested_actions', 'confidence', 'human_review_required', 'request_id',
            'provider', 'model', 'tokens_input', 'tokens_output', 'fallback_used',
            'fallback_provider', 'error_code', 'metadata',
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
            cost: isset($extra['cost']) ? (float) $extra['cost'] : $this->cost,
            cached: $this->cached,
            requestId: $extra['request_id'] ?? $this->requestId,
            status: $extra['status'] ?? $this->status,
            findings: $extra['findings'] ?? $this->findings,
            severity: $extra['severity'] ?? $this->severity,
            evidence: $extra['evidence'] ?? $this->evidence,
            suggestedActions: $extra['suggested_actions'] ?? $this->suggestedActions,
            confidence: isset($extra['confidence']) ? (float) $extra['confidence'] : $this->confidence,
            humanReviewRequired: (bool) ($extra['human_review_required'] ?? $this->humanReviewRequired),
            provider: array_key_exists('provider', $extra) ? $extra['provider'] : $this->provider,
            model: array_key_exists('model', $extra) ? $extra['model'] : $this->model,
            inputTokens: isset($extra['tokens_input']) ? (int) $extra['tokens_input'] : $this->inputTokens,
            outputTokens: isset($extra['tokens_output']) ? (int) $extra['tokens_output'] : $this->outputTokens,
            fallbackUsed: (bool) ($extra['fallback_used'] ?? $this->fallbackUsed),
            fallbackProvider: array_key_exists('fallback_provider', $extra) ? $extra['fallback_provider'] : $this->fallbackProvider,
            errorCode: array_key_exists('error_code', $extra) ? $extra['error_code'] : $this->errorCode,
            metadata: array_merge($this->metadata, (array) ($extra['metadata'] ?? [])),
        );
    }

    private function highestSeverity(array $findings): ?string
    {
        $rank = ['critical' => 4, 'high' => 3, 'warning' => 2, 'medium' => 2, 'info' => 1, 'low' => 1];
        $highest = null;
        $score = 0;
        foreach ($findings as $finding) {
            if (! is_array($finding)) continue;
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
