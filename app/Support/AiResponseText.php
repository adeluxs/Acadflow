<?php

namespace App\Support;

final class AiResponseText
{
    private static bool $inHumanize = false;

    public static function clean(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') return '';

        // If a provider returned a raw JSON object (often a structured draft
        // without an `answer` field), convert it into readable prose instead of
        // dumping JSON into the UI.
        if (! self::$inHumanize) {
            $humanized = self::humanizeJson($text);
            if ($humanized !== null && $humanized !== '') {
                return $humanized;
            }
        }

        $text = preg_replace('/^```(?:json|JSON|markdown|md)?\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Hide internal grounding/source citation labels from user-facing prose.
        $text = preg_replace('/\s*\[S\d+\]/u', '', $text) ?? $text;

        $lines = preg_split('/\n/u', $text) ?: [$text];
        $cleaned = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Drop markdown horizontal rules and empty heading-only markers.
            if (preg_match('/^[-*_]{3,}$/u', $line) === 1) {
                $cleaned[] = '';
                continue;
            }

            // Convert markdown headings/lists into plain readable text.
            $line = preg_replace('/^#{1,6}\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^>\s?/u', '', $line) ?? $line;
            $line = preg_replace('/^[-*+]\s+/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[.)]\s+/u', '', $line) ?? $line;

            // Remove inline markdown emphasis/code/link syntax while keeping text.
            $line = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $line) ?? $line;
            $line = preg_replace('/__([^_]+)__/u', '$1', $line) ?? $line;
            $line = preg_replace('/\*([^*]+)\*/u', '$1', $line) ?? $line;
            $line = preg_replace('/_([^_]+)_/u', '$1', $line) ?? $line;
            $line = preg_replace('/`([^`]+)`/u', '$1', $line) ?? $line;
            $line = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1', $line) ?? $line;
            $line = preg_replace('/\s*\[S\d+\]/u', '', $line) ?? $line;

            $cleaned[] = trim($line);
        }

        $text = implode("\n", $cleaned);
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Convert a JSON object/array string into readable plain text.
     *
     * Returns null when the value is not JSON so callers can fall back to their
     * normal text handling. Prefers an explicit answer field when present.
     */
    public static function humanizeJson(?string $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || ($text[0] !== '{' && $text[0] !== '[')) {
            return null;
        }

        $text = preg_replace('/^```(?:json|JSON)?\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            return null;
        }

        self::$inHumanize = true;
        try {
            foreach (['answer', 'response', 'content', 'text', 'summary', 'message'] as $key) {
                $candidate = $decoded[$key] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    return self::clean($candidate);
                }
            }

            return trim(self::renderArray($decoded));
        } finally {
            self::$inHumanize = false;
        }
    }

    /** @param array<mixed> $data */
    private static function renderArray(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $label = is_string($key) ? self::label($key) : null;

            if (is_array($value)) {
                $scalars = array_filter($value, static fn ($item): bool => ! is_array($item));

                if (array_is_list($value) && $scalars !== [] && count($scalars) === count($value)) {
                    if ($label !== null) $lines[] = $label.':';
                    foreach ($value as $item) {
                        $lines[] = '- '.self::scalar($item);
                    }
                    continue;
                }

                $lines[] = $label !== null ? $label.':' : '';
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $block = self::renderArray($item);
                            if ($block !== '') $lines[] = $block;
                        } else {
                            $lines[] = '- '.self::scalar($item);
                        }
                    }
                } else {
                    $block = self::renderArray($value);
                    if ($block !== '') $lines[] = $block;
                }
                continue;
            }

            $scalar = self::scalar($value);
            if ($scalar === '') continue;
            $lines[] = $label !== null ? $label.': '.$scalar : $scalar;
        }

        return implode("\n", array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
    }

    private static function label(string $key): string
    {
        return ucfirst(str_replace('_', ' ', trim($key)));
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if ($value === null) return '';
        if (is_scalar($value)) return trim((string) $value);
        return '';
    }
}
