<?php

namespace App\Ai\Rules;

/**
 * Assignment Rule Pack - checks structure expected for general assignments/seminars.
 */
class AssignmentRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'assignment';
    }

    public function label(): string
    {
        return 'Assignment Structure';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $type = $context['type'] ?? 'assignment';

        if (! in_array($type, ['assignment', 'seminar', 'group'])) {
            return $issues;
        }

        $lower = strtolower($text);

        $requiredSections = [
            'introduction' => 'Introduction',
            'conclusion' => 'Conclusion',
            'reference' => 'References',
        ];

        foreach ($requiredSections as $needle => $label) {
            if (! str_contains($lower, $needle)) {
                $issues[] = $this->issue(
                    'missing_'.$needle,
                    "Missing required section: {$label}.",
                    'warning',
                    2,
                    "Add a clearly labelled {$label} section.",
                    'structure'
                );
            }
        }

        return $this->result($issues);
    }
}
