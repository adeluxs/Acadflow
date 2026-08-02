<?php

namespace App\Ai\Rules;

/**
 * Project Rule Pack - validates full final-year project / report structure.
 */
class ProjectRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'project';
    }

    public function label(): string
    {
        return 'Final Year Project';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $type = $context['type'] ?? '';

        if (! in_array($type, ['project'])) {
            return $issues;
        }

        $lower = strtolower($text);

        $required = [
            'abstract' => 'Abstract',
            'table of contents' => 'Table of Contents',
            'introduction' => 'Introduction',
            'literature review' => 'Literature Review',
            'methodology' => 'Methodology',
            'result' => 'Results',
            'discussion' => 'Discussion',
            'conclusion' => 'Conclusion',
            'reference' => 'References',
            'appendix' => 'Appendices',
            'acknowledg' => 'Acknowledgements',
        ];

        $missing = [];
        foreach ($required as $needle => $label) {
            if (! str_contains($lower, $needle)) {
                $missing[] = $label;
            }
        }

        if (! empty($missing)) {
            $issues[] = $this->issue(
                'missing_project_chapters',
                'Missing expected project chapters: '.implode(', ', $missing).'.',
                'critical',
                1,
                'Add the missing chapters to meet the standard project report structure.',
                'structure'
            );
        }

        // Empty section detection (heading immediately followed by next heading)
        if (preg_match('/(chapter|section)\s+[\divxlcdm]+\s*[^\n]*\n\s*(chapter|section)\s+/i', $text)) {
            $issues[] = $this->issue(
                'empty_section',
                'One or more sections appear to be empty (heading with no content).',
                'warning',
                2,
                'Ensure every chapter/section contains substantive content.',
                'structure'
            );
        }

        return $this->result($issues);
    }
}
