<?php

namespace App\Ai\Rules;

/**
 * Academic Rule Pack - general academic quality checks shared across submission types.
 */
class AcademicRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'academic';
    }

    public function label(): string
    {
        return 'Academic Standards';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $text = $this->extractText($context);
        $minWords = (int) $this->setting('ai_min_word_count', 200);
        $maxWords = (int) $this->setting('ai_max_word_count', 20000);

        if ($text === '') {
            return $this->result([$this->issue(
                'no_text',
                'No readable text content was found. Upload a text-based PDF/DOCX or paste content.',
                'critical',
                1,
                'Ensure the document contains extractable text (not a scanned image-only PDF).',
                'content'
            )]);
        }

        $words = $this->wordCount($text);
        $context['word_count'] = $words;

        if ($words < $minWords) {
            $issues[] = $this->issue(
                'low_word_count',
                "The document appears very short ({$words} words). Academic submissions usually require more depth.",
                'warning',
                3,
                "Expand your work to meet the expected length (at least {$minWords} words).",
                'content'
            );
        }

        if ($maxWords > 0 && $words > $maxWords) {
            $issues[] = $this->issue(
                'high_word_count',
                "The document is very long ({$words} words).",
                'info',
                5,
                'Confirm this length is appropriate for the submission type.',
                'content'
            );
        }

        // Vague writing detection
        $vague = ['thing', 'stuff', 'something', 'somewhere', 'very', 'really', 'a lot', 'etc.'];
        $hits = 0;
        foreach ($vague as $term) {
            $hits += preg_match_all('/\b'.preg_quote($term, '/').'\b/i', $text);
        }
        if ($hits >= 5) {
            $issues[] = $this->issue(
                'vague_language',
                "Detected {$hits} uses of vague language (thing, stuff, very, etc.).",
                'info',
                5,
                'Replace vague terms with specific, precise academic language.',
                'clarity'
            );
        }

        return $this->result($issues, ['word_count' => $words]);
    }

    protected function extractText(array $context): string
    {
        return $context['text'] ?? $context['content'] ?? '';
    }
}
