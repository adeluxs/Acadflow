<?php

namespace App\Services\Ai;

use App\Services\Discovery\LocalEmbeddingService;
use App\Services\SettingService;
use App\Models\User;

/**
 * Validates grounded answers before they are shown to a user.
 *
 * A provider response is not trusted merely because it contains one citation.
 * We check citation range, sentence citation coverage, and whether cited
 * sentences are reasonably supported by the excerpts they cite.
 */
class GroundedAnswerValidator
{
    private const STOP_WORDS = [
        'a','an','and','are','as','at','be','been','being','but','by','can','could','did','do','does','for','from','had','has','have','he','her','him','his','how','i','if','in','into','is','it','its','may','me','more','most','my','of','on','or','our','please','should','so','some','than','that','the','their','them','then','there','these','they','this','those','to','us','was','we','were','what','when','where','which','who','why','will','with','would','you','your',
    ];

    public function __construct(private readonly LocalEmbeddingService $embeddings) {}

    /**
     * @param list<array<string,mixed>> $sources
     * @return array<string,mixed>
     */
    public function validate(string $answer, array $sources, User $user): array
    {
        $answer = trim($answer);
        if ($answer === '' || $sources === []) {
            return [
                'valid' => false,
                'citation_coverage' => 0.0,
                'support_score' => 0.0,
                'reasons' => ['empty_answer_or_sources'],
            ];
        }

        $sourceCount = count($sources);
        $reasons = [];
        preg_match_all('/\[S(\d+)\]/u', $answer, $allCitations);
        $citationNumbers = array_map('intval', $allCitations[1] ?? []);
        if ($citationNumbers === []) {
            $reasons[] = 'missing_citations';
        }
        foreach ($citationNumbers as $number) {
            if ($number < 1 || $number > $sourceCount) {
                $reasons[] = 'invalid_citation_reference';
                break;
            }
        }

        if (preg_match('/https?:\/\//iu', $answer) === 1) {
            $allSourceText = implode(' ', array_map(fn (array $source): string => (string) ($source['excerpt'] ?? ''), $sources));
            preg_match_all('/https?:\/\/[^\s)\]]+/iu', $answer, $answerUrls);
            foreach ($answerUrls[0] ?? [] as $url) {
                if (! str_contains($allSourceText, $url)) {
                    $reasons[] = 'uncited_external_url';
                    break;
                }
            }
        }

        if (preg_match('/\b(?:I searched the web|from the internet|according to my general knowledge|outside (?:the )?publication)\b/iu', $answer) === 1) {
            $reasons[] = 'claims_external_knowledge';
        }

        $sentences = $this->sentences($answer);
        $substantive = array_values(array_filter($sentences, fn (string $sentence): bool => count($this->terms($sentence)) >= 3));
        $citedCount = 0;
        $supportedCount = 0;
        $supportValues = [];

        foreach ($substantive as $sentence) {
            preg_match_all('/\[S(\d+)\]/u', $sentence, $matches);
            $refs = array_values(array_unique(array_filter(array_map('intval', $matches[1] ?? []), fn (int $n): bool => $n >= 1 && $n <= $sourceCount)));
            if ($refs === []) {
                continue;
            }

            $citedCount++;
            $sentenceWithoutCitations = trim(preg_replace('/\[S\d+\]/u', '', $sentence) ?? $sentence);
            $best = 0.0;
            foreach ($refs as $ref) {
                $excerpt = (string) ($sources[$ref - 1]['excerpt'] ?? '');
                if ($excerpt === '') continue;
                $lexical = $this->lexicalSupport($sentenceWithoutCitations, $excerpt);
                $semantic = max(0.0, $this->embeddings->cosine(
                    $this->embeddings->embed($sentenceWithoutCitations),
                    $this->embeddings->embed($excerpt)
                ));
                $score = min(1.0, ($lexical * 0.62) + ($semantic * 0.38));
                $best = max($best, $score);
            }
            $supportValues[] = $best;
        }

        $coverage = $substantive === [] ? 1.0 : ($citedCount / count($substantive));
        $supportThreshold = min(0.95, max(0.02, (float) SettingService::get('ai_grounded_support_threshold', 0.20, $user->university_id)));
        foreach ($supportValues as $value) {
            if ($value >= $supportThreshold) $supportedCount++;
        }
        $supportCoverage = $supportValues === [] ? 0.0 : ($supportedCount / count($supportValues));
        $averageSupport = $supportValues === [] ? 0.0 : (array_sum($supportValues) / count($supportValues));
        $minCitationCoverage = min(1.0, max(0.2, (float) SettingService::get('ai_grounded_citation_coverage_min', 0.85, $user->university_id)));
        $minSupportCoverage = min(1.0, max(0.2, (float) SettingService::get('ai_grounded_support_coverage_min', 0.70, $user->university_id)));

        if ($coverage < $minCitationCoverage) {
            $reasons[] = 'insufficient_citation_coverage';
        }
        if ($supportCoverage < $minSupportCoverage) {
            $reasons[] = 'weak_source_support';
        }

        return [
            'valid' => $reasons === [],
            'citation_coverage' => round($coverage, 4),
            'support_coverage' => round($supportCoverage, 4),
            'support_score' => round($averageSupport, 4),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn (string $sentence): bool => $sentence !== ''));
    }

    private function lexicalSupport(string $sentence, string $excerpt): float
    {
        $terms = $this->terms($sentence);
        if ($terms === []) return 0.0;
        $haystack = array_fill_keys($this->terms($excerpt), true);
        $matched = 0;
        foreach ($terms as $term) {
            if (isset($haystack[$term])) $matched++;
        }
        return min(1.0, $matched / max(1, count($terms)));
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($tokens, fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true))));
    }
}
