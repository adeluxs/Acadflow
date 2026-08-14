<?php

namespace App\Ai\Rules;

use App\Ai\Support\PdfDocumentAnalyzer;
use Illuminate\Support\Facades\Storage;

/**
 * Layout Rule Pack - validates PDF submissions against configurable layout
 * requirements (font, page size, margins, spacing, page numbering).
 *
 * Falls back to heuristic checks when no explicit requirements are set.
 *
 * Non-PDF submissions are skipped because layout checks require rendered
 * page geometry.
 */
class LayoutRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'layout';
    }

    public function label(): string
    {
        return 'Layout & Formatting';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $submission = $context['submission'] ?? null;
        $requirements = $context['layout_requirements'] ?? null;

        if (! $submission) {
            return $this->result($issues);
        }

        // Collect PDF file paths from submission versions
        $pdfPaths = [];
        foreach ($submission->versions ?? [] as $version) {
            $path = $version->file_path ?? null;
            if ($path && str_ends_with(strtolower($path), '.pdf') && Storage::disk($version->disk ?: config('filesystems.default', 'local'))->exists($path)) {
                $pdfPaths[] = ['path' => $path, 'disk' => $version->disk];
            }
        }

        if ($pdfPaths === []) {
            return $this->result($issues);
        }

        $analyzer = new PdfDocumentAnalyzer;
        $allFindings = [];

        foreach ($pdfPaths as $pdfFile) {
            $finding = $analyzer->analyzeFromPath($pdfFile['path'], $pdfFile['disk']);
            if ($finding) {
                $allFindings[] = $finding;
            }
        }

        if ($allFindings === []) {
            return $this->result($issues);
        }

        // If explicit requirements are set, check against them.
        // Otherwise fall back to generic heuristic checks.
        if ($requirements) {
            $issues = array_merge($issues, $this->checkRequirements($allFindings, $requirements));
        }

        $issues = array_merge($issues, $this->checkHeuristics($allFindings));

        return $this->result($issues);
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @param array<string, mixed>       $requirements
     *
     * @return list<array<string, mixed>>
     */
    private function checkRequirements(array $findings, array $requirements): array
    {
        $issues = [];

        // --- Required fonts ---
        $requiredFonts = array_filter((array) ($requirements['required_fonts'] ?? []), fn ($f) => $f !== '');
        if ($requiredFonts !== []) {
            $usedFonts = [];
            foreach ($findings as $finding) {
                $usedFonts = array_merge($usedFonts, $finding['unique_normalized_fonts'] ?? []);
            }
            $usedFonts = array_values(array_unique($usedFonts));

            foreach ($requiredFonts as $required) {
                $found = false;
                foreach ($usedFonts as $used) {
                    if (strcasecmp($used, $required) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $issues[] = $this->issue(
                        'missing_required_font',
                        "Required font '{$required}' not detected. Document uses: ".implode(', ', array_slice($usedFonts, 0, 5)).'.',
                        'warning',
                        2,
                        "Apply '{$required}' as the primary font per the submission requirements.",
                        'layout'
                    );
                }
            }
        }

        // --- Page size ---
        $requiredPageSize = $requirements['page_size'] ?? null;
        if ($requiredPageSize) {
            $sizes = [];
            foreach ($findings as $finding) {
                foreach ($finding['page_sizes'] ?? [] as $size) {
                    $sizes[] = $size['label'] ?? 'unknown';
                }
            }
            $uniqueSizes = array_unique($sizes);
            foreach ($uniqueSizes as $sizeLabel) {
                if ($sizeLabel !== $requiredPageSize && ! str_contains($sizeLabel, $requiredPageSize)) {
                    $issues[] = $this->issue(
                        'wrong_page_size',
                        "Page size '{$sizeLabel}' does not match required size '{$requiredPageSize}'.",
                        'warning',
                        3,
                        "Set all pages to '{$requiredPageSize}' before submission.",
                        'layout'
                    );
                    break;
                }
            }
        }

        // --- Margins ---
        $minMarginInches = $requirements['min_margin_inches'] ?? null;
        if ($minMarginInches !== null && $minMarginInches > 0) {
            $minMarginPt = $minMarginInches * 72;
            foreach ($findings as $finding) {
                $positions = $finding['text_positions'] ?? [];
                if ($positions === []) {
                    continue;
                }
                $xs = array_column($positions, 'x');
                $ys = array_column($positions, 'y');
                $minX = min($xs);
                $maxX = max($xs);
                $minY = min($ys);
                $maxY = max($ys);
                $pageWidth = $finding['page_sizes'][0]['width'] ?? 595;
                $pageHeight = $finding['page_sizes'][0]['height'] ?? 842;
                $marginLeft = $minX;
                $marginRight = $pageWidth - $maxX;
                $marginTop = $pageHeight - $maxY;
                $marginBottom = $minY;
                $tightest = min($marginLeft, $marginRight, $marginTop, $marginBottom);

                if ($tightest < $minMarginPt) {
                    $issues[] = $this->issue(
                        'margin_too_small',
                        "Minimum margin is ~".round($tightest / 72, 2).' inches; required: '.$minMarginInches.' inches.',
                        'warning',
                        3,
                        'Increase margins to at least '.$minMarginInches.' inches on all sides.',
                        'layout'
                    );
                    break;
                }
            }
        }

        // --- Page numbering ---
        if (! empty($requirements['require_page_numbering'])) {
            foreach ($findings as $finding) {
                if (! ($finding['has_page_numbering'] ?? false)) {
                    $issues[] = $this->issue(
                        'missing_page_numbering',
                        'Page numbering is required but was not detected.',
                        'warning',
                        4,
                        'Add page numbers per the submission requirements.',
                        'layout'
                    );
                    break;
                }
            }
        }

        // --- Institution branding ---
        if (! empty($requirements['require_institution_branding'])) {
            // Branding check is text-based; reuse the TemplateRulePack check
            // by flagging it here if the submission text lacks institution name.
            // This is intentionally left to the TemplateRulePack to avoid
            // coupling layout checks to course/university lookups.
        }

        return $issues;
    }

    /**
     * @param list<array<string, mixed>> $findings
     *
     * @return list<array<string, mixed>>
     */
    private function checkHeuristics(array $findings): array
    {
        $issues = [];

        // --- Page size consistency ---
        $pageSizes = [];
        foreach ($findings as $finding) {
            foreach ($finding['page_sizes'] ?? [] as $size) {
                $pageSizes[] = $size['label'] ?? 'unknown';
            }
        }
        $uniqueSizes = array_unique($pageSizes);
        if (count($uniqueSizes) > 1) {
            $issues[] = $this->issue(
                'inconsistent_page_size',
                'Inconsistent page sizes detected: '.implode(', ', $uniqueSizes).'.',
                'info',
                5,
                'Use a consistent page size (preferably A4 or Letter) throughout the document.',
                'layout'
            );
        }

        // --- Font consistency (heuristic) ---
        $allFonts = [];
        foreach ($findings as $finding) {
            $allFonts = array_merge($allFonts, $finding['unique_normalized_fonts'] ?? []);
        }
        $uniqueFonts = array_values(array_unique($allFonts));
        if (count($uniqueFonts) > 4) {
            $issues[] = $this->issue(
                'many_font_families',
                'Detected '.count($uniqueFonts).' distinct font families: '.implode(', ', array_slice($uniqueFonts, 0, 5)).'.',
                'info',
                5,
                'Use 1-2 font families consistently throughout the document.',
                'layout'
            );
        }

        // --- Line spacing consistency ---
        $allSpacings = [];
        foreach ($findings as $finding) {
            $allSpacings = array_merge($allSpacings, $finding['line_spacings'] ?? []);
        }
        if ($allSpacings !== []) {
            $avgSpacing = array_sum($allSpacings) / count($allSpacings);
            $variance = 0.0;
            foreach ($allSpacings as $spacing) {
                $variance += ($spacing - $avgSpacing) ** 2;
            }
            $stdDev = sqrt($variance / count($allSpacings));
            if ($stdDev > 4.0) {
                $issues[] = $this->issue(
                    'inconsistent_line_spacing',
                    'Line spacing appears inconsistent (standard deviation: '.round($stdDev, 1).'pt).',
                    'info',
                    6,
                    'Use consistent line spacing (e.g. 1.5 or double) throughout the document.',
                    'layout'
                );
            }
        }

        return $issues;
    }
}
