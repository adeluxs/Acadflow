<?php

namespace App\Ai\Rules;

/**
 * Template & Layout Rule Pack - validates document against expected academic
 * templates, institution-specific formatting requirements, and basic layout
 * conventions (page numbering, signature lines, etc.).
 *
 * Note: True margin / font-size / line-spacing detection requires PDF metadata
 * or rendered page analysis, which is outside the scope of text-only rule
 * packs. This pack focuses on structural template conformance detectable
 * from extracted text.
 */
class TemplateRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'template';
    }

    public function label(): string
    {
        return 'Template & Layout';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $lower = strtolower($text);
        $submission = $context['submission'] ?? null;
        $course = $context['course'] ?? null;
        $type = $context['type'] ?? '';

        // --- Expected section sequences by document type ---
        $expectedSections = match ($type) {
            'project' => [
                'abstract', 'table of contents', 'introduction',
                'literature review', 'methodology', 'results',
                'discussion', 'conclusion', 'references', 'appendix',
            ],
            'siwes' => [
                'introduction', 'organization', 'logbook',
                'conclusion', 'recommendation', 'references',
            ],
            'seminar', 'assignment' => [
                'abstract', 'introduction', 'methodology',
                'results', 'discussion', 'conclusion', 'references',
            ],
            default => [],
        };

        if (! empty($expectedSections)) {
            $missing = [];
            foreach ($expectedSections as $section) {
                if (! str_contains($lower, $section)) {
                    $missing[] = $section;
                }
            }
            if (! empty($missing)) {
                $issues[] = $this->issue(
                    'template_missing_sections',
                    'Document template for '.$type.' is missing expected sections: '.implode(', ', $missing).'.',
                    'warning',
                    3,
                    'Ensure your document follows the standard '.ucfirst($type).' template structure.',
                    'template'
                );
            }
        }

        // --- Approval / signature page ---
        if (in_array($type, ['project', 'siwes', 'seminar'])) {
            if (! preg_match('/supervisor|approved|signature|declaration/i', $text)) {
                $issues[] = $this->issue(
                    'missing_approval_page',
                    'No approval/signature page detected. '.ucfirst($type).' documents typically require supervisor approval.',
                    'warning',
                    4,
                    'Add the approval page with supervisor signature lines.',
                    'template'
                );
            }
        }

        // --- Page numbering indicators ---
        if (! preg_match('/page\s+\d+|p\.\s*\d+|^\s*\d+\s*$/mi', $text)) {
            $issues[] = $this->issue(
                'missing_page_numbering',
                'No page numbering indicators detected.',
                'info',
                6,
                'Ensure pages are numbered according to your institution\'s guidelines.',
                'template'
            );
        }

        // --- University/institution branding ---
        $university = $course?->department?->faculty?->university ?? null;
        if ($university && ! empty($university->name)) {
            $nameParts = array_filter(explode(' ', $university->name), fn ($p) => strlen($p) > 3);
            $foundBranding = false;
            foreach ($nameParts as $part) {
                if (preg_match('/'.preg_quote($part, '/').'/i', $text)) {
                    $foundBranding = true;
                    break;
                }
            }
            if (! $foundBranding) {
                $issues[] = $this->issue(
                    'missing_institution_branding',
                    'Document does not appear to contain the institution name ('.$university->name.').',
                    'info',
                    6,
                    'Include your institution\'s name on the title/cover page per formatting guidelines.',
                    'template'
                );
            }
        }

        // --- Table of figures / tables (if figures/tables exist) ---
        $hasFigures = preg_match_all('/figure\s+\d+/i', $text);
        $hasTables = preg_match_all('/table\s+\d+/i', $text);
        if (($hasFigures > 0 || $hasTables > 0) && ! str_contains($lower, 'list of figures') && ! str_contains($lower, 'list of tables')) {
            $issues[] = $this->issue(
                'missing_list_of_figures_tables',
                'Figures or tables are present but no List of Figures or List of Tables was detected.',
                'info',
                5,
                'Add a List of Figures and/or List of Tables before the main content.',
                'template'
            );
        }

        return $this->result($issues);
    }
}
