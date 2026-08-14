<?php

namespace App\Services\AcademicIntegrity;

use App\Contracts\AcademicIntegrity\SimilarityProviderInterface;
use Illuminate\Support\Facades\Http;

class ExternalSimilarityProvider implements SimilarityProviderInterface
{
    public function __construct(private readonly array $configuration) {}
    public function name(): string { return (string) ($this->configuration['name'] ?? 'external'); }
    public function available(): bool { return filled($this->configuration['endpoint'] ?? null) && filled($this->configuration['api_key'] ?? null); }

    public function compare(string $text, array $context = []): array
    {
        if (! $this->available()) {
            throw new \RuntimeException("Similarity provider {$this->name()} is not configured.");
        }

        $response = Http::timeout((int) ($this->configuration['timeout'] ?? 60))
            ->retry(2, 500)
            ->withToken($this->configuration['api_key'])
            ->acceptJson()
            ->post($this->configuration['endpoint'], ['text' => $text, 'context' => $context])
            ->throw()->json();

        return [
            'score' => (float) ($response['score'] ?? $response['similarity_score'] ?? 0),
            'summary' => (string) ($response['summary'] ?? 'External similarity check completed.'),
            'matches' => collect($response['matches'] ?? [])->map(fn ($match) => [
                'source_type' => $match['source_type'] ?? 'external',
                'source_identifier' => $match['id'] ?? $match['source_identifier'] ?? null,
                'source_title' => $match['title'] ?? null,
                'source_url' => $match['url'] ?? null,
                'source_hash' => $match['hash'] ?? null,
                'source_excerpt' => $match['excerpt'] ?? null,
                'target_locations' => $match['locations'] ?? [],
                'similarity_score' => (float) ($match['score'] ?? 0),
                'citation_status' => $match['citation_status'] ?? 'unknown',
                'metadata' => $match,
            ])->all(),
            'metadata' => ['provider_response_id' => $response['id'] ?? null],
        ];
    }
}
