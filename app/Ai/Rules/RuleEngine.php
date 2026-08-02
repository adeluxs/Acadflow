<?php

namespace App\Ai\Rules;

use App\Ai\Contracts\AiResponse;

/**
 * The Rule-Based AI Engine.
 *
 * This is the default AI implementation. It is provider-independent and runs
 * entirely offline. It produces structured responses identical in shape to the
 * external providers so feature modules never branch on the source.
 */
class RuleEngine
{
    /**
     * @return array<RulePackInterface>
     */
    public function packs(): array
    {
        return [
            new AcademicRulePack,
            new AssignmentRulePack,
            new ProjectRulePack,
            new SiwesRulePack,
            new CitationRulePack,
            new FormattingRulePack,
            new TemplateRulePack,
            new LayoutRulePack,
            new DeadlineRulePack,
            new InstitutionRulePack,
            new DiscussionRulePack,
            new PlagiarismRulePack,
        ];
    }

    /**
     * Resolve a pack by feature/type so we can run only relevant packs.
     */
    public function packsForFeature(string $feature, array $context): array
    {
        $all = $this->packs();
        $type = $context['type'] ?? null;

        return array_values(array_filter($all, function (RulePackInterface $pack) use ($feature) {
            if (! $pack->enabled()) {
                return false;
            }

            return match ($feature) {
                'submission_validator' => in_array($pack->key(), [
                    'academic', 'assignment', 'project', 'siwes', 'citation', 'formatting', 'template', 'layout', 'deadline', 'institution',
                ], true),
                'citation_assistant' => $pack->key() === 'citation',
                'writing_assistant' => in_array($pack->key(), ['academic', 'formatting'], true),
                'siwes_assistant' => $pack->key() === 'siwes',
                'project_assistant' => in_array($pack->key(), ['project', 'academic', 'citation'], true),
                'discussion_assistant' => $pack->key() === 'discussion',
                'plagiarism' => $pack->key() === 'plagiarism',
                default => true,
            };
        }));
    }

    /**
     * Run the rule engine for a feature and return a standardized response.
     */
    public function run(string $feature, array $context): AiResponse
    {
        $start = microtime(true);
        $issues = [];
        $data = ['word_count' => $context['word_count'] ?? null];

        foreach ($this->packsForFeature($feature, $context) as $pack) {
            try {
                $result = $pack->analyze($context);

                // Packs return ['issues' => [...], 'data' => [...]] so that
                // metrics (word_count, similarity, ...) persist into the response.
                $result = $this->normalizeResult($result);

                $issues = array_merge($issues, $result['issues']);
                $data = array_merge($data, $result['data']);
            } catch (\Throwable $e) {
                // A failing pack must never break the analysis.
                report($e);
            }
        }

        $issues = $this->prioritize($issues);
        $score = $this->readinessScore($issues, $context);

        return new AiResponse(
            source: 'rule_engine',
            feature: $feature,
            success: true,
            data: $data,
            summary: $this->summarize($issues, $score),
            score: $score,
            issues: $issues,
            processingTime: round(microtime(true) - $start, 4),
            cost: 0.0,
        );
    }

    /**
     * Normalize a pack's return value into the ['issues','data'] shape,
     * tolerating legacy plain-issue-list returns.
     */
    protected function normalizeResult(array $result): array
    {
        if (array_key_exists('issues', $result) || array_key_exists('data', $result)) {
            return [
                'issues' => $result['issues'] ?? [],
                'data' => $result['data'] ?? [],
            ];
        }

        // Legacy: a bare list of issues.
        return ['issues' => $result, 'data' => []];
    }

    /**
     * Sort by priority (1 highest) then severity.
     */
    protected function prioritize(array $issues): array
    {
        $severityRank = ['critical' => 0, 'warning' => 1, 'info' => 2];

        usort($issues, function ($a, $b) use ($severityRank) {
            $pa = $a['priority'] ?? 5;
            $pb = $b['priority'] ?? 5;
            if ($pa === $pb) {
                return ($severityRank[$a['severity'] ?? 'info'] ?? 2)
                    <=> ($severityRank[$b['severity'] ?? 'info'] ?? 2);
            }

            return $pa <=> $pb;
        });

        return $issues;
    }

    /**
     * Compute a 0-100 readiness score; more/severe issues lower the score.
     */
    protected function readinessScore(array $issues, array $context): float
    {
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += match ($issue['severity'] ?? 'info') {
                'critical' => 18,
                'warning' => 8,
                default => 3,
            };
        }

        return max(0, min(100, 100 - $penalty));
    }

    protected function summarize(array $issues, float $score): string
    {
        $critical = count(array_filter($issues, fn ($i) => ($i['severity'] ?? '') === 'critical'));
        $warnings = count(array_filter($issues, fn ($i) => ($i['severity'] ?? '') === 'warning'));

        if (empty($issues)) {
            return "Readiness score {$score}/100. No issues detected. Submission looks ready.";
        }

        return "Readiness score {$score}/100. Found {$critical} critical and {$warnings} warning issue(s).";
    }
}
