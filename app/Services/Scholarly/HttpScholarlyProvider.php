<?php

namespace App\Services\Scholarly;

use App\Contracts\Scholarly\ScholarlyProviderInterface;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpScholarlyProvider implements ScholarlyProviderInterface
{
    public function __construct(private readonly string $provider, private readonly array $config) {}

    public function name(): string
    {
        return $this->provider;
    }

    public function available(): bool
    {
        return filled($this->config['base_url'] ?? null);
    }

    /**
     * Synchronous compatibility path used by identifier lookups and any legacy callers.
     * Interactive multi-provider discovery uses enqueueSearch()/parsePooledSearch()
     * through ScholarlyDiscoveryService so provider latency is bounded concurrently.
     */
    public function search(string $query, array $filters = [], int $limit = 20): array
    {
        if (! $this->available()) {
            return [];
        }

        return match ($this->provider) {
            'openalex' => $this->openAlex($query, $filters, $limit),
            'crossref' => $this->crossref($query, $filters, $limit),
            'semantic_scholar' => $this->semanticScholar($query, $filters, $limit),
            'core' => $this->core($query, $filters, $limit),
            'doaj' => $this->doaj($query, $filters, $limit),
            'pubmed' => $this->pubmed($query, $filters, $limit),
            'arxiv' => $this->arxiv($query, $filters, $limit),
            default => [],
        };
    }

    public function find(string $identifier): ?array
    {
        return collect($this->search($identifier, ['identifier' => $identifier], 5))
            ->first(fn ($record) => in_array($identifier, [$record['external_identifier'] ?? null, $record['doi'] ?? null], true));
    }

    /**
     * Queue the provider's first network request into Laravel's HTTP request pool.
     * Every request is intentionally short-lived and has no retry chain so a slow
     * scholarly index cannot consume the entire PHP request budget.
     */
    public function enqueueSearch(Pool $pool, string $query, array $filters = [], int $limit = 20): mixed
    {
        $limit = max(1, min($limit, 20));
        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $request = $pool
            ->as($this->provider)
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout());

        return match ($this->provider) {
            'openalex' => $request->acceptJson()->get($base.'/works', [
                'search' => $query,
                'per-page' => $limit,
                'mailto' => $this->config['mailto'] ?? null,
            ]),
            'crossref' => $request->acceptJson()->get($base.'/works', [
                'query.bibliographic' => $query,
                'rows' => $limit,
                'mailto' => $this->config['mailto'] ?? null,
            ]),
            'semantic_scholar' => $request
                ->acceptJson()
                ->withHeaders(array_filter(['x-api-key' => $this->config['api_key'] ?? null]))
                ->get($base.'/paper/search', [
                    'query' => $query,
                    'limit' => min($limit, 100),
                    'fields' => 'paperId,title,authors,year,externalIds,url,abstract',
                ]),
            'core' => blank($this->config['api_key'] ?? null)
                ? null
                : $request
                    ->acceptJson()
                    ->withToken((string) $this->config['api_key'])
                    ->post($base.'/search/works', ['q' => $query, 'limit' => $limit]),
            'doaj' => $request->acceptJson()->get($base.'/search/articles/'.rawurlencode($query), ['pageSize' => $limit]),
            'pubmed' => $request->acceptJson()->get($base.'/esearch.fcgi', [
                'db' => 'pubmed',
                'term' => $query,
                'retmax' => $limit,
                'retmode' => 'json',
            ]),
            'arxiv' => $request->get($base.'/api/query', [
                'search_query' => 'all:'.$query,
                'start' => 0,
                'max_results' => $limit,
            ]),
            default => null,
        };
    }

    /**
     * Normalize a response returned by the concurrent pool. PubMed requires one
     * dependent summary request after its ID search; that second wave is also
     * strictly bounded and never retried.
     */
    public function parsePooledSearch(mixed $response, array $filters = [], int $limit = 20): array
    {
        if ($response instanceof Throwable || ! $response instanceof Response || $response->failed()) {
            return [];
        }

        return match ($this->provider) {
            'openalex' => $this->normalizeOpenAlex((array) $response->json()),
            'crossref' => $this->normalizeCrossref((array) $response->json()),
            'semantic_scholar' => $this->normalizeSemanticScholar((array) $response->json()),
            'core' => $this->normalizeCore((array) $response->json()),
            'doaj' => $this->normalizeDoaj((array) $response->json()),
            'pubmed' => $this->normalizePubmedSearch((array) $response->json(), min($limit, 20)),
            'arxiv' => $this->normalizeArxiv((string) $response->body()),
            default => [],
        };
    }

    private function request(string $url, array $query = [], array $headers = []): mixed
    {
        $http = Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withHeaders($headers);

        if ($key = $this->config['api_key'] ?? null) {
            $http = $http->withToken($key);
        }

        $response = $http->get($url, $query)->throw();
        $type = $response->header('content-type', '');

        return str_contains($type, 'json') ? $response->json() : $response->body();
    }

    private function openAlex(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/works', [
            'search' => $query,
            'per-page' => $limit,
            'mailto' => $this->config['mailto'] ?? null,
        ]);

        return $this->normalizeOpenAlex((array) $json);
    }

    private function crossref(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/works', [
            'query.bibliographic' => $query,
            'rows' => $limit,
            'mailto' => $this->config['mailto'] ?? null,
        ]);

        return $this->normalizeCrossref((array) $json);
    }

    private function semanticScholar(string $query, array $filters, int $limit): array
    {
        $json = Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withHeaders(array_filter(['x-api-key' => $this->config['api_key'] ?? null]))
            ->get(rtrim($this->config['base_url'], '/').'/paper/search', [
                'query' => $query,
                'limit' => min($limit, 100),
                'fields' => 'paperId,title,authors,year,externalIds,url,abstract',
            ])
            ->throw()
            ->json();

        return $this->normalizeSemanticScholar((array) $json);
    }

    private function core(string $query, array $filters, int $limit): array
    {
        if (blank($this->config['api_key'] ?? null)) {
            return [];
        }

        $json = Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->withToken((string) $this->config['api_key'])
            ->post(rtrim($this->config['base_url'], '/').'/search/works', ['q' => $query, 'limit' => $limit])
            ->throw()
            ->json();

        return $this->normalizeCore((array) $json);
    }

    private function doaj(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/search/articles/'.rawurlencode($query), ['pageSize' => $limit]);

        return $this->normalizeDoaj((array) $json);
    }

    private function pubmed(string $query, array $filters, int $limit): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $search = $this->request($base.'/esearch.fcgi', [
            'db' => 'pubmed',
            'term' => $query,
            'retmax' => $limit,
            'retmode' => 'json',
        ]);

        return $this->normalizePubmedSearch((array) $search, $limit);
    }

    private function arxiv(string $query, array $filters, int $limit): array
    {
        $xml = Http::connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->get(rtrim($this->config['base_url'], '/').'/api/query', [
                'search_query' => 'all:'.$query,
                'start' => 0,
                'max_results' => $limit,
            ])
            ->throw()
            ->body();

        return $this->normalizeArxiv((string) $xml);
    }

    private function normalizeOpenAlex(array $json): array
    {
        return collect($json['results'] ?? [])->map(fn ($r) => $this->normalize(
            $r['id'] ?? '',
            $r['title'] ?? '',
            $r['authorships'] ?? [],
            $r['publication_year'] ?? null,
            $r['doi'] ?? null,
            $r['primary_location']['landing_page_url'] ?? null,
            $this->reconstructAbstract($r['abstract_inverted_index'] ?? []),
            $r,
        ))->all();
    }

    private function normalizeCrossref(array $json): array
    {
        return collect($json['message']['items'] ?? [])->map(fn ($r) => $this->normalize(
            $r['DOI'] ?? '',
            $r['title'][0] ?? '',
            $r['author'] ?? [],
            $r['published']['date-parts'][0][0] ?? null,
            $r['DOI'] ?? null,
            $r['URL'] ?? null,
            $r['abstract'] ?? null,
            $r,
        ))->all();
    }

    private function normalizeSemanticScholar(array $json): array
    {
        return collect($json['data'] ?? [])->map(fn ($r) => $this->normalize(
            $r['paperId'] ?? '',
            $r['title'] ?? '',
            $r['authors'] ?? [],
            $r['year'] ?? null,
            $r['externalIds']['DOI'] ?? null,
            $r['url'] ?? null,
            $r['abstract'] ?? null,
            $r,
        ))->all();
    }

    private function normalizeCore(array $json): array
    {
        return collect($json['results'] ?? [])->map(fn ($r) => $this->normalize(
            (string) ($r['id'] ?? ''),
            $r['title'] ?? '',
            $r['authors'] ?? [],
            $r['yearPublished'] ?? null,
            $r['doi'] ?? null,
            $r['downloadUrl'] ?? null,
            $r['abstract'] ?? null,
            $r,
        ))->all();
    }

    private function normalizeDoaj(array $json): array
    {
        return collect($json['results'] ?? [])->map(function ($r) {
            $doi = collect($r['bibjson']['identifier'] ?? [])->firstWhere('type', 'doi')['id'] ?? null;

            return $this->normalize(
                $r['id'] ?? '',
                $r['bibjson']['title'] ?? '',
                $r['bibjson']['author'] ?? [],
                $r['bibjson']['year'] ?? null,
                $doi,
                $r['bibjson']['link'][0]['url'] ?? null,
                $r['bibjson']['abstract'] ?? null,
                $r,
            );
        })->all();
    }

    private function normalizePubmedSearch(array $search, int $limit): array
    {
        $ids = array_slice((array) ($search['esearchresult']['idlist'] ?? []), 0, max(1, min($limit, 20)));
        if ($ids === []) {
            return [];
        }

        try {
            $base = rtrim($this->config['base_url'], '/');
            $summary = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->acceptJson()
                ->get($base.'/esummary.fcgi', [
                    'db' => 'pubmed',
                    'id' => implode(',', $ids),
                    'retmode' => 'json',
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            report($exception);
            return [];
        }

        return collect($ids)->map(function ($id) use ($summary) {
            $r = $summary['result'][$id] ?? [];
            $doi = collect($r['articleids'] ?? [])->firstWhere('idtype', 'doi')['value'] ?? null;

            return $this->normalize(
                (string) $id,
                $r['title'] ?? '',
                $r['authors'] ?? [],
                isset($r['pubdate']) ? (int) substr($r['pubdate'], 0, 4) : null,
                $doi,
                'https://pubmed.ncbi.nlm.nih.gov/'.$id.'/',
                null,
                $r,
            );
        })->all();
    }

    private function normalizeArxiv(string $xml): array
    {
        $feed = @simplexml_load_string($xml);
        if (! $feed) {
            return [];
        }

        return collect($feed->entry ?? [])->map(function ($entry) {
            $authors = [];
            foreach ($entry->author ?? [] as $author) {
                $authors[] = ['name' => (string) $author->name];
            }

            return $this->normalize(
                (string) $entry->id,
                trim((string) $entry->title),
                $authors,
                (int) substr((string) $entry->published, 0, 4),
                null,
                (string) $entry->id,
                trim((string) $entry->summary),
                json_decode(json_encode($entry), true) ?: [],
            );
        })->all();
    }

    private function normalize(string $id, string $title, array $authors, ?int $year, ?string $doi, ?string $url, ?string $abstract, array $raw): array
    {
        return [
            'provider' => $this->provider,
            'external_identifier' => $id,
            'record_type' => 'work',
            'title' => $title ?: 'Untitled scholarly work',
            'authors' => $authors,
            'publication_year' => $year,
            'doi' => $doi ? preg_replace('#^https?://doi.org/#', '', $doi) : null,
            'url' => $url,
            'abstract' => $abstract,
            'concepts' => [],
            'raw_data' => $raw,
        ];
    }

    private function reconstructAbstract(array $inverted): ?string
    {
        if ($inverted === []) {
            return null;
        }

        $words = [];
        foreach ($inverted as $word => $positions) {
            foreach ($positions as $position) {
                $words[(int) $position] = $word;
            }
        }
        ksort($words);

        return implode(' ', $words);
    }

    private function timeout(): int
    {
        return max(2, min((int) ($this->config['timeout'] ?? config('scholarly.search.timeout', 5)), 8));
    }

    private function connectTimeout(): int
    {
        return max(1, min((int) ($this->config['connect_timeout'] ?? config('scholarly.search.connect_timeout', 2)), 4));
    }
}
