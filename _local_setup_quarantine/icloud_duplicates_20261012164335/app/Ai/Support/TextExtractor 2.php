<?php

namespace App\Ai\Support;

use App\Models\SubmissionVersion;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Extracts plain text from authorized stored files without assuming that the
 * configured default disk owns every document.
 */
class TextExtractor
{
    public function fromVersion(SubmissionVersion $version): string
    {
        if ($version->mediaAsset && ! in_array($version->mediaAsset->scan_status, ['clean', 'skipped'], true)) {
            return '';
        }

        return $this->fromPath($version->file_path, $version->disk);
    }

    public function fromPath(string $path, ?string $disk = null): string
    {
        $storage = Storage::disk($disk ?: config('filesystems.default', 'local'));
        if (! $storage->exists($path)) {
            return '';
        }

        $lower = strtolower($path);
        $mime = $storage->mimeType($path) ?? '';
        $content = (string) $storage->get($path);

        if (str_ends_with($lower, '.txt') || str_contains($mime, 'text/plain')) {
            return $content;
        }

        if (str_ends_with($lower, '.pdf') || str_contains($mime, 'pdf')) {
            return $this->fromPdfContent($content);
        }

        if (str_ends_with($lower, '.docx')) {
            return $this->fromDocxContent($content);
        }

        return $this->stripBinary($content);
    }

    protected function fromPdfContent(string $raw): string
    {
        try {
            if (class_exists(Parser::class)) {
                $pdf = (new Parser)->parseContent($raw);
                $text = $pdf->getText();
                if (trim($text) !== '') {
                    return $text;
                }
            }
        } catch (\Throwable) {
            // Fall through to a conservative best-effort extraction.
        }

        return $this->stripBinary($raw);
    }

    protected function fromDocxContent(string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'acadflow-docx-');
        if ($tmp === false) {
            return '';
        }

        try {
            file_put_contents($tmp, $content);
            $zip = new \ZipArchive;
            if ($zip->open($tmp) !== true) {
                return '';
            }
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();

            return $this->stripDocxXml($xml);
        } catch (\Throwable) {
            return '';
        } finally {
            @unlink($tmp);
        }
    }

    protected function stripDocxXml(string $xml): string
    {
        $text = preg_replace('/<w:p[ >]/', "\n", $xml);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim((string) preg_replace('/\n{2,}/', "\n", $text));
    }

    protected function stripBinary(string $content): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $content);

        return trim((string) preg_replace('/\s+/', ' ', (string) $text));
    }
}
