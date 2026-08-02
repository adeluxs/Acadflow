<?php

namespace App\Ai\Rules;

use App\Models\Department;
use App\Services\SettingService;

/**
 * Base helper for rule packs providing shared utilities and a settings-backed
 * enabled() implementation. Department-specific rules can be toggled too.
 */
abstract class BaseRulePack implements RulePackInterface
{
    protected function setting(string $key, mixed $default = null): mixed
    {
        return SettingService::get($key, $default);
    }

    public function enabled(): bool
    {
        return (bool) $this->setting('ai_rulepack_'.$this->key(), true);
    }

    /**
     * Build a normalized issue array.
     */
    protected function issue(
        string $code,
        string $message,
        string $severity,
        int $priority,
        string $suggestion,
        string $category
    ): array {
        return compact('code', 'message', 'severity', 'priority', 'suggestion', 'category');
    }

    /**
     * Build a standardized pack result. Packs return issues + optional
     * structured data so metrics (word_count, similarity, ...) persist.
     */
    protected function result(array $issues = [], array $data = []): array
    {
        return ['issues' => $issues, 'data' => $data];
    }

    /**
     * Count words in a block of text.
     */
    protected function wordCount(string $text): int
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/', $text));
    }

    /**
     * Detect headings (lines beginning with numbering like "1.", "1.2", "Chapter 1").
     */
    protected function detectHeadings(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $headings = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(chapter|section|appendix)\s+[\divxlcdm]+/i', $line)
                || preg_match('/^(\d+\.)+\d*\s+[A-Z]/', $line)
                || preg_match('/^\d+\.\s+[A-Z]/', $line)) {
                $headings[] = $line;
            }
        }

        return $headings;
    }

    /**
     * Resolve the department for a submission context, if present.
     */
    protected function department(array $context): ?Department
    {
        $submission = $context['submission'] ?? null;
        $course = $submission?->course ?? $context['course'] ?? null;

        return $course?->department ?? null;
    }
}
