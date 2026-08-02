<?php

namespace App\Ai\Rules;

/**
 * Citation Rule Pack - detects missing references list, uncited references,
 * duplicate references, inconsistent citation markers, broken URLs/DOIs, and
 * mixed citation styles.
 */
class CitationRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'citation';
    }

    public function label(): string
    {
        return 'Citation & References';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $context['text'] ?? $context['content'] ?? '';
        $lower = strtolower($text);

        if (! str_contains($lower, 'reference') && ! str_contains($lower, 'bibliography')) {
            $issues[] = $this->issue(
                'missing_reference_list',
                'No reference or bibliography section detected.',
                'critical',
                1,
                'Add a properly formatted References/Bibliography section.',
                'citation'
            );

            return $this->result($issues);
        }

        // In-text citation markers: APA (Author, 2020), IEEE [1], Harvard, etc.
        $apa = preg_match_all('/\([A-Z][a-z]+,?\s*\d{4}\)/', $text);
        $ieee = preg_match_all('/\[\d+\]/', $text);
        $inText = $apa + $ieee;

        if ($inText === 0) {
            $issues[] = $this->issue(
                'no_in_text_citations',
                'No in-text citations detected (e.g. (Author, 2020) or [1]).',
                'warning',
                2,
                'Cite sources within the body text to support your claims.',
                'citation'
            );
        }

        // --- Citation style inconsistency ---
        $hasApa = $apa > 0;
        $hasIeee = $ieee > 0;
        if ($hasApa && $hasIeee) {
            $issues[] = $this->issue(
                'citation_style_inconsistency',
                'Mixed citation styles detected (both APA-style (Author, Year) and IEEE-style [1] markers found).',
                'warning',
                3,
                'Use a single citation style consistently throughout the document.',
                'citation'
            );
        }

        // --- Duplicate references ---
        $uniqueRefs = [];
        preg_match_all('/\([A-Z][a-z]+,?\s*(\d{4})\)/', $text, $refMatches);
        foreach ($refMatches[1] ?? [] as $year) {
            $uniqueRefs[$year] = ($uniqueRefs[$year] ?? 0) + 1;
        }
        foreach ($uniqueRefs as $year => $count) {
            if ($count > 3) {
                $issues[] = $this->issue(
                    'duplicate_reference',
                    "Year {$year} appears {$count} times in citations — possible duplicate references.",
                    'warning',
                    3,
                    'Check that each reference list entry is unique and all citations map correctly.',
                    'citation'
                );
                break;
            }
        }

        // --- Broken / invalid URLs ---
        preg_match_all('/https?:\/\/[^\s)\]]+/i', $text, $urlMatches);
        foreach ($urlMatches[0] ?? [] as $url) {
            if (preg_match('/\s/', $url) || ! preg_match('/^https?:\/\/.+\..+/', $url)) {
                $issues[] = $this->issue(
                    'broken_url',
                    'A URL appears to be malformed: '.$url,
                    'warning',
                    4,
                    'Verify all URLs are complete and correctly formatted.',
                    'citation'
                );
                break;
            }
        }

        // --- DOI format validation ---
        if (preg_match('/doi/i', $lower)) {
            if (! preg_match('/10\.\d{4,9}\/[^\s)\]]+/i', $text)) {
                $issues[] = $this->issue(
                    'invalid_doi',
                    'A DOI is mentioned but does not appear to be correctly formatted.',
                    'info',
                    5,
                    'Use the full DOI format, e.g. https://doi.org/10.1000/xyz123.',
                    'citation'
                );
            }
        }

        return $this->result($issues);
    }
}
