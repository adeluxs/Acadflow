<?php

namespace App\Ai\Rules;

/**
 * A rule pack encapsulates a coherent set of validation/analysis rules.
 *
 * Rule packs are independently enabled/disabled from system settings and can
 * examine the same shared context to add issues to the validation report.
 */
interface RulePackInterface
{
    /**
     * Machine key of the pack, also used as the setting key suffix.
     */
    public function key(): string;

    /**
     * Human friendly label.
     */
    public function label(): string;

    /**
     * Whether this pack is enabled in system settings.
     */
    public function enabled(): bool;

    /**
     * Analyze the context and return a structured result.
     *
     * Return value: [
     *   'issues' => list of issue arrays (see shape below),
     *   'data'   => arbitrary structured payload merged into the response
     *                 (e.g. word_count, similarity metrics).
     * ]
     *
     * Each issue: [
     *   'code' => string,
     *   'message' => string,
     *   'severity' => 'critical'|'warning'|'info',
     *   'priority' => int (1 = highest),
     *   'suggestion' => string,
     *   'category' => string,
     * ]
     */
    public function analyze(array $context): array;
}
