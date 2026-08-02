<?php

namespace App\Ai\Rules;

/**
 * Formatting Rule Pack - heading structure, missing figures/tables/TOC,
 * chapter ordering, title-page detection, and basic layout heuristics.
 */
class FormattingRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'formatting';
    }

    public function label(): string
    {
        return 'Formatting & Structure';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $lower = strtolower($text);
        $headings = $this->detectHeadings($text);

        // --- Heading order ---
        if (! empty($headings)) {
            $order = [];
            foreach ($headings as $h) {
                if (preg_match('/^(\d+)\./', $h, $m)) {
                    $order[] = (int) $m[1];
                }
            }
            for ($i = 1; $i < count($order); $i++) {
                if ($order[$i] < $order[$i - 1]) {
                    $issues[] = $this->issue(
                        'incorrect_chapter_order',
                        'Chapter numbering is not in ascending order (chapter '.$order[$i].' follows '.$order[$i - 1].').',
                        'warning',
                        3,
                        'Reorder chapters so they follow a logical ascending sequence.',
                        'formatting'
                    );
                    break;
                }
            }
        }

        // --- Heading correctness ---
        if (! empty($headings)) {
            $headingTexts = array_map('strtolower', $headings);
            $suspicious = 0;
            foreach ($headingTexts as $h) {
                if (preg_match('/chapter\s+\d+/', $h) && ! preg_match('/chapter\s+\d+\s+/', $h)) {
                    $suspicious++;
                }
            }
            if ($suspicious > 0) {
                $issues[] = $this->issue(
                    'incorrect_heading_format',
                    "Detected {$suspicious} heading(s) with unusual formatting (e.g. missing space after chapter number).",
                    'info',
                    5,
                    'Ensure headings follow a consistent format, e.g. "Chapter 1 Introduction".',
                    'formatting'
                );
            }
        }

        // --- Title page / cover detection ---
        if (! str_contains($lower, 'title') && ! str_contains($lower, 'declaration')) {
            $issues[] = $this->issue(
                'missing_title_page',
                'No title page or declaration page indicators detected.',
                'info',
                4,
                'Add the standard cover/title and declaration pages required by your institution.',
                'formatting'
            );
        }

        // --- Table of Contents ---
        if (! str_contains($lower, 'table of contents') && ! str_contains($lower, 'contents')) {
            $issues[] = $this->issue(
                'missing_table_of_contents',
                'No Table of Contents section detected.',
                'warning',
                4,
                'Include a Table of Contents listing all chapters and sections.',
                'structure'
            );
        }

        // --- Figures ---
        if (! preg_match('/figure\s+\d+|fig\.\s*\d+|figure\s*:\s*/i', $text)) {
            $issues[] = $this->issue(
                'missing_figures',
                'No figures detected. Academic submissions often require diagrams, charts, or illustrations.',
                'info',
                6,
                'Consider adding relevant figures to support your analysis.',
                'structure'
            );
        }

        // --- Tables ---
        if (! preg_match('/table\s+\d+|table\s*:\s*/i', $text)) {
            $issues[] = $this->issue(
                'missing_tables',
                'No tables detected. Academic submissions may require tabulated data.',
                'info',
                6,
                'Consider adding relevant tables to present data clearly.',
                'structure'
            );
        }

        // --- Empty section detection ---
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
