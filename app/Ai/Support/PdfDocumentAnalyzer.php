<?php

namespace App\Ai\Support;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;

/**
 * Lightweight PDF layout inspector built on top of smalot/pdfparser.
 *
 * Extracts page dimensions, font usage, text coordinates, line spacing,
 * and footer/page-numbering patterns — all from the raw PDF, without
 * requiring rendered page images or external services.
 *
 * Returns null when the file is missing, not a PDF, or cannot be parsed.
 */
class PdfDocumentAnalyzer
{
    /**
     * Mapping of PDF font subset tags / PostScript names to normalized
     * human-readable family names.
     *
     * @var array<string, string>
     */
    private const FONT_NORMALIZATIONS = [
        // Times New Roman variants
        'TimesNewRomanPS-BoldMT' => 'Times New Roman',
        'TimesNewRomanPS-BoldItalicMT' => 'Times New Roman',
        'TimesNewRomanPS-ItalicMT' => 'Times New Roman',
        'TimesNewRomanPS-Regular' => 'Times New Roman',
        'TimesNewRomanPS' => 'Times New Roman',
        'TimesNewRoman' => 'Times New Roman',
        'Times-Roman' => 'Times New Roman',
        'Times-Bold' => 'Times New Roman',
        'Times-Italic' => 'Times New Roman',
        'Times-BoldItalic' => 'Times New Roman',

        // Arial variants
        'Arial-BoldMT' => 'Arial',
        'Arial-ItalicMT' => 'Arial',
        'Arial-BoldItalicMT' => 'Arial',
        'ArialMT' => 'Arial',
        'Arial' => 'Arial',

        // Helvetica variants
        'Helvetica-Bold' => 'Helvetica',
        'Helvetica-Oblique' => 'Helvetica',
        'Helvetica-BoldOblique' => 'Helvetica',
        'Helvetica' => 'Helvetica',

        // Courier variants
        'Courier-Bold' => 'Courier New',
        'Courier-Oblique' => 'Courier New',
        'Courier-BoldOblique' => 'Courier New',
        'Courier' => 'Courier New',

        // Calibri variants
        'Calibri-Bold' => 'Calibri',
        'Calibri-Italic' => 'Calibri',
        'Calibri-BoldItalic' => 'Calibri',
        'Calibri' => 'Calibri',

        // Georgia variants
        'Georgia-Bold' => 'Georgia',
        'Georgia-Italic' => 'Georgia',
        'Georgia-BoldItalic' => 'Georgia',
        'Georgia' => 'Georgia',

        // Verdana variants
        'Verdana-Bold' => 'Verdana',
        'Verdana-Italic' => 'Verdana',
        'Verdana-BoldItalic' => 'Verdana',
        'Verdana' => 'Verdana',
    ];

    /**
     * Analyze a stored file path.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeFromPath(string $path): ?array
    {
        if (! Storage::exists($path)) {
            return null;
        }

        $lower = strtolower($path);
        $mime = Storage::mimeType($path) ?? '';

        if (! str_ends_with($lower, '.pdf') && ! str_contains($mime, 'pdf')) {
            return null;
        }

        return $this->analyzeContent((string) Storage::get($path));
    }

    /**
     * Analyze raw PDF content bytes.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeContent(string $pdfContent): ?array
    {
        if (class_exists(Parser::class) === false) {
            return null;
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseContent($pdfContent);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $pages = $pdf->getPages();
        if ($pages === []) {
            return null;
        }

        $summary = [
            'page_count' => count($pages),
            'page_sizes' => [],
            'fonts' => [],
            'normalized_fonts' => [],
            'font_sizes' => [],
            'text_positions' => [],
            'line_spacings' => [],
            'footer_like_text' => [],
        ];

        foreach ($pages as $page) {
            if (! $page instanceof Page) {
                continue;
            }

            $this->analyzePage($page, $summary);
        }

        $summary['unique_fonts'] = array_values(array_unique($summary['fonts']));
        $summary['unique_normalized_fonts'] = array_values(array_unique($summary['normalized_fonts']));
        $summary['unique_font_sizes'] = array_values(array_unique($summary['font_sizes']));

        // Derive simple layout hints
        $summary['has_page_numbering'] = $this->detectPageNumbering($summary['footer_like_text']);
        $summary['consistent_font_count'] = count($summary['unique_normalized_fonts']);
        $summary['average_line_spacing'] = $this->averageLineSpacing($summary['line_spacings']);

        return $summary;
    }

    /**
     * Normalize a raw PDF font name to a standard family name.
     */
    public function normalizeFontName(string $rawName): string
    {
        $trimmed = trim($rawName);

        if (isset(self::FONT_NORMALIZATIONS[$trimmed])) {
            return self::FONT_NORMALIZATIONS[$trimmed];
        }

        // Strip common PDF subset prefix: ABCDEF+FontName
        if (preg_match('/^[A-F0-9]+\+(.+)$/', $trimmed, $m)) {
            $trimmed = $m[1];
        }

        // Strip variant suffixes after a hyphen or plus
        $base = preg_split('/[-+]/', $trimmed)[0] ?? $trimmed;

        // Check the base name against known normalizations
        if (isset(self::FONT_NORMALIZATIONS[$base])) {
            return self::FONT_NORMALIZATIONS[$base];
        }

        return ucfirst(strtolower($base));
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function analyzePage(Page $page, array &$summary): void
    {
        $size = $this->pageSize($page);
        $summary['page_sizes'][] = $size;

        // Fonts
        foreach ($page->getFonts() as $font) {
            if ($font instanceof Font) {
                $name = $font->getName();
                if ($name && $name !== Font::MISSING) {
                    $summary['fonts'][] = $name;
                    $summary['normalized_fonts'][] = $this->normalizeFontName($name);
                }
            }
        }

        // Text coordinates and font sizes from the Text Matrix
        $dataTm = $page->getDataTm();
        $pageHeight = $size['height'];
        $yValues = [];
        foreach ($dataTm as $item) {
            if (! isset($item[0][4], $item[0][5])) {
                continue;
            }
            $x = (float) $item[0][4];
            $y = (float) $item[0][5];
            $yValues[] = $y;

            $summary['text_positions'][] = ['x' => $x, 'y' => $y, 'page' => $page->getPageNumber()];

            if (isset($item[3])) {
                $summary['font_sizes'][] = (float) $item[3];
            }

            // Footer-like text: anything below the bottom 15% of the page
            if ($pageHeight > 0 && $y < ($pageHeight * 0.15)) {
                $text = is_string($item[1]) ? $item[1] : '';
                $text = trim($text);
                if ($text !== '') {
                    $summary['footer_like_text'][] = $text;
                }
            }
        }

        // Line spacing from consecutive Y values
        $yValues = array_values(array_unique(array_sort($yValues)));
        for ($i = 1; $i < count($yValues); $i++) {
            $delta = abs($yValues[$i] - $yValues[$i - 1]);
            if ($delta > 0.5) {
                $summary['line_spacings'][] = $delta;
            }
        }
    }

    /**
     * @return array{width: float, height: float, label: string}
     */
    private function pageSize(Page $page): array
    {
        $box = $page->get('CropBox');
        if ($box instanceof \Smalot\PdfParser\Element\ElementMissing) {
            $box = $page->get('MediaBox');
        }

        if ($box instanceof \Smalot\PdfParser\Element\ElementArray) {
            $values = $box->getContent();
            if (is_array($values) && count($values) >= 4) {
                $width = (float) ($values[2] ?? 595) - (float) ($values[0] ?? 0);
                $height = (float) ($values[3] ?? 842) - (float) ($values[1] ?? 0);

                return [
                    'width' => round($width, 2),
                    'height' => round($height, 2),
                    'label' => $this->pageLabel($width, $height),
                ];
            }
        }

        $details = $page->getDetails();
        if (isset($details['MediaBox']) && is_array($details['MediaBox'])) {
            $values = $details['MediaBox'];
            if (count($values) >= 4) {
                $width = round((float) $values[2] - (float) $values[0], 2);
                $height = round((float) $values[3] - (float) $values[1], 2);

                return [
                    'width' => $width,
                    'height' => $height,
                    'label' => $this->pageLabel($width, $height),
                ];
            }
        }

        return ['width' => 595.0, 'height' => 842.0, 'label' => 'A4 (default)'];
    }

    private function pageLabel(float $width, float $height): string
    {
        $w = round($width);
        $h = round($height);

        $sizes = [
            'A4' => [595, 842],
            'Letter' => [612, 792],
            'Legal' => [612, 1008],
            'A3' => [842, 1191],
            'A5' => [420, 595],
        ];

        foreach ($sizes as $label => [$sw, $sh]) {
            if (abs($w - $sw) <= 10 && abs($h - $sh) <= 10) {
                return $label;
            }
            if (abs($w - $sh) <= 10 && abs($h - $sw) <= 10) {
                return $label.' (landscape)';
            }
        }

        return sprintf('%g x %g pt', $w, $h);
    }

    /**
     * Heuristic: detect page-numbering patterns in footer text.
     */
    private function detectPageNumbering(array $footerTexts): bool
    {
        $unique = array_unique(array_map('trim', $footerTexts));
        $pageNumbers = 0;
        foreach ($unique as $text) {
            if (preg_match('/^page\s+\d+$/i', $text)
                || preg_match('/^\d+$/', $text)
                || preg_match('/^-\s*\d+\s*-$/', $text)
            ) {
                $pageNumbers++;
            }
        }

        return $pageNumbers >= 5;
    }

    private function averageLineSpacing(array $spacings): float
    {
        if ($spacings === []) {
            return 0.0;
        }

        return round(array_sum($spacings) / count($spacings), 2);
    }
}
