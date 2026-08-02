<?php

namespace App\Ai\Contracts;

/**
 * Standardized response returned by every AI provider and the rule engine.
 *
 * Structured identically whether the result comes from the Rule Engine or an
 * external provider, so feature code never needs to branch on source.
 */
final class AiResponse
{
    /**
     * @param  string  $source  'rule_engine' | provider name
     * @param  string  $feature  The AI feature key that produced this
     * @param  bool  $success  Whether processing succeeded
     * @param  array  $data  Feature-specific structured payload
     * @param  string|null  $summary  Human readable summary
     * @param  float  $score  Optional 0-100 score (readiness, similarity, etc.)
     * @param  array  $issues  List of issue objects (validator/assistant outputs)
     * @param  float|null  $processingTime  Seconds taken
     * @param  float|null  $cost  Estimated cost in USD
     * @param  bool  $cached  Whether served from cache
     */
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
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'feature' => $this->feature,
            'success' => $this->success,
            'summary' => $this->summary,
            'score' => $this->score,
            'issues' => $this->issues,
            'data' => $this->data,
            'processing_time' => $this->processingTime,
            'cost' => $this->cost,
            'cached' => $this->cached,
        ];
    }

    /**
     * Merge external structured data into this response.
     *
     * Recognized meta keys ('source', 'cost') override the corresponding fields
     * so callers can stamp a fallback/provider source and real cost.
     */
    public function withData(array $extra): self
    {
        $source = $extra['source'] ?? $this->source;
        $cost = $extra['cost'] ?? $this->cost;
        $data = array_diff_key($extra, array_flip(['source', 'cost']));

        return new self(
            source: $source,
            feature: $this->feature,
            success: $this->success,
            data: array_merge($this->data, $data),
            summary: $this->summary,
            score: $this->score,
            issues: $this->issues,
            processingTime: $this->processingTime,
            cost: $cost,
            cached: $this->cached,
        );
    }
}
