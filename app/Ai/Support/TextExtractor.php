<?php

namespace App\Ai\Support;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Extracts plain text from submission files for AI analysis.
 *
 * Supports TXT directly, attempts PDF text extraction via the existing dompdf
 * tooling / simple stripHtml fallback, and DOCX via zip + XML. Falls back to an
 * empty string when text cannot be extracted (e.g. scanned PDFs).
 */
class TextExtractor
{
    /**
     * Extract text from a stored file path.
     */
    public function fromPath(string $path): string
    {
        if (! Storage::exists($path)) {
            return '';
        }

        $lower = strtolower($path);
        $mime = Storage::mimeType($path) ?? '';

        if (str_ends_with($lower, '.txt') || str_contains($mime, 'text/plain')) {
            return (string) Storage::get($path);
        }

        if (str_ends_with($lower, '.pdf') || str_contains($mime, 'pdf')) {
            return $this->fromPdf($path);
        }

        if (str_ends_with($lower, '.docx')) {
            return $this->fromDocx($path);
        }

        if (str_ends_with($lower, '.doc')) {
            // Legacy .doc is binary; best-effort strip
            return $this->stripBinary((string) Storage::get($path));
        }

        return $this->stripBinary((string) Storage::get($path));
    }

    protected function fromPdf(string $path): string
    {
        $raw = (string) Storage::get($path);

        try {
            // Prefer smalot/pdfparser for real text extraction.
            if (class_exists(Parser::class)) {
                $parser = new Parser;
                $pdf = $parser->parseContent($raw);
                $text = $pdf->getText();

                // Image-only / scanned PDFs yield little or no text.
                if (trim($text) !== '') {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            // fall through to best-effort strip
        }

        // Best-effort extraction of any embedded strings. Returns '' when the
        // PDF is scanned/image-only so callers can report "no readable text"
        // instead of crashing.
        return $this->stripBinary($raw);
    }

    protected function fromDocx(string $path): string
    {
        try {
            $content = Storage::get($path);
            $tmp = tempnam(sys_get_temp_dir(), 'docx');
            file_put_contents($tmp, $content);

            $zip = new \ZipArchive;
            if ($zip->open($tmp) === true) {
                $xml = $zip->getFromName('word/document.xml') ?: '';
                $zip->close();
                unlink($tmp);

                return $this->stripDocxXml($xml);
            }
            unlink($tmp);
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    protected function stripDocxXml(string $xml): string
    {
        $text = preg_replace('/<w:p[ >]/', "\n", $xml);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace('/\n{2,}/', "\n", $text));
    }

    protected function stripBinary(string $content): string
    {
        // Best-effort: remove nulls and control chars, keep printable text.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $content);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }
}
