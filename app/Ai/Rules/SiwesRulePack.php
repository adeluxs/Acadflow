<?php

namespace App\Ai\Rules;

/**
 * SIWES Rule Pack - validates Student Industrial Work Experience Scheme reports.
 */
class SiwesRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'siwes';
    }

    public function label(): string
    {
        return 'SIWES Report';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $type = $context['type'] ?? '';

        if ($type !== 'siwes') {
            return $issues;
        }

        $lower = strtolower($text);

        $required = [
            'introduction' => 'Introduction',
            'organization' => 'Organization / Company Details',
            'logbook' => 'Logbook / Daily Activities',
            'conclusion' => 'Conclusion',
            'recommendation' => 'Recommendations',
        ];

        foreach ($required as $needle => $label) {
            if (! str_contains($lower, $needle)) {
                $issues[] = $this->issue(
                    'missing_siwes_'.$needle,
                    "Missing expected SIWES section: {$label}.",
                    'warning',
                    2,
                    "Add the '{$label}' section required for SIWES reports.",
                    'structure'
                );
            }
        }

        // Supervisor information check
        if (! preg_match('/supervis(or|ed by)/i', $text)) {
            $issues[] = $this->issue(
                'missing_siwes_supervisor',
                'No supervisor information detected in the report.',
                'info',
                4,
                'Include the name and details of your industrial supervisor.',
                'content'
            );
        }

        return $this->result($issues);
    }
}
