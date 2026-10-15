<?php

namespace App\Services\AcademicIntegrity;

use App\Contracts\AcademicIntegrity\SimilarityProviderInterface;
use App\Models\SearchDocument;

class InternalSimilarityProvider implements SimilarityProviderInterface
{
    public function name(): string { return 'internal'; }
    public function available(): bool { return true; }

    public function compare(string $text, array $context = []): array
    {
        $own = $this->shingles($text);
        if ($own === []) {
            return ['score' => 0.0, 'summary' => 'Not enough text for a reliable internal comparison.', 'matches' => []];
        }

        $query = SearchDocument::query()->whereNotNull('body');
        if ($universityId = $context['university_id'] ?? null) {
            $query->where(fn ($q) => $q->whereNull('university_id')->orWhere('university_id', $universityId));
        }
        if (($excludeType = $context['exclude_type'] ?? null) && ($excludeId = $context['exclude_id'] ?? null)) {
            $query->where(fn ($q) => $q->where('searchable_type', '!=', $excludeType)->orWhere('searchable_id', '!=', $excludeId));
        }

        $matches = $query->latest('indexed_at')->limit((int) ($context['corpus_limit'] ?? 500))->get()
            ->map(function (SearchDocument $document) use ($own): ?array {
                $source = $this->shingles((string) $document->body);
                if ($source === []) {
                    return null;
                }
                $overlap = array_values(array_intersect(array_keys($own), array_keys($source)));
                $score = count($overlap) / max(1, min(count($own), count($source))) * 100;
                if ($score < 1) {
                    return null;
                }

                return [
                    'source_type' => $document->searchable_type,
                    'source_identifier' => (string) $document->searchable_id,
                    'source_title' => $document->title,
                    'source_url' => null,
                    'source_hash' => $document->checksum,
                    'source_excerpt' => mb_substr((string) $document->body, 0, 1000),
                    'target_locations' => [['locator' => 'document', 'matched_shingles' => count($overlap)]],
                    'similarity_score' => round($score, 2),
                    'citation_status' => 'unknown',
                    'metadata' => ['search_document_uuid' => $document->uuid],
                ];
            })->filter()->sortByDesc('similarity_score')->take(20)->values()->all();

        $score = (float) collect($matches)->max('similarity_score');
        return [
            'score' => round($score, 2),
            'summary' => $matches === [] ? 'No meaningful internal matches were detected.' : count($matches).' internal source match(es) were detected.',
            'matches' => $matches,
            'metadata' => ['corpus' => 'authorized AcadFlow search index', 'algorithm' => 'five-word-shingle-overlap'],
        ];
    }

    private function shingles(string $text): array
    {
        $words = preg_split('/\s+/', mb_strtolower(trim(strip_tags($text))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, fn ($word) => mb_strlen($word) > 2));
        if (count($words) < 20) {
            return [];
        }
        $result = [];
        for ($i = 0; $i <= count($words) - 5; $i++) {
            $phrase = implode(' ', array_slice($words, $i, 5));
            $result[hash('sha256', $phrase)] = $phrase;
        }
        return $result;
    }
}
