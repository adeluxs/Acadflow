<?php

namespace App\Services\Scholarly;

use App\Contracts\Scholarly\ScholarlyProviderInterface;
use Illuminate\Support\Facades\Http;

class HttpScholarlyProvider implements ScholarlyProviderInterface
{
    public function __construct(private readonly string $provider, private readonly array $config) {}
    public function name(): string { return $this->provider; }
    public function available(): bool { return filled($this->config['base_url'] ?? null); }

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

    private function request(string $url, array $query = [], array $headers = []): mixed
    {
        $http = Http::timeout((int) ($this->config['timeout'] ?? 20))->retry(2, 300)->acceptJson()->withHeaders($headers);
        if ($key = $this->config['api_key'] ?? null) {
            $http = $http->withToken($key);
        }
        $response = $http->get($url, $query)->throw();
        $type = $response->header('content-type', '');
        return str_contains($type, 'json') ? $response->json() : $response->body();
    }

    private function openAlex(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/works', ['search' => $query, 'per-page' => $limit, 'mailto' => $this->config['mailto'] ?? null]);
        return collect($json['results'] ?? [])->map(fn ($r) => $this->normalize($r['id'] ?? '', $r['title'] ?? '', $r['authorships'] ?? [], $r['publication_year'] ?? null, $r['doi'] ?? null, $r['primary_location']['landing_page_url'] ?? null, $this->reconstructAbstract($r['abstract_inverted_index'] ?? []), $r))->all();
    }

    private function crossref(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/works', ['query.bibliographic' => $query, 'rows' => $limit, 'mailto' => $this->config['mailto'] ?? null]);
        return collect($json['message']['items'] ?? [])->map(fn ($r) => $this->normalize($r['DOI'] ?? '', $r['title'][0] ?? '', $r['author'] ?? [], $r['published']['date-parts'][0][0] ?? null, $r['DOI'] ?? null, $r['URL'] ?? null, $r['abstract'] ?? null, $r))->all();
    }

    private function semanticScholar(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/paper/search', ['query' => $query, 'limit' => min($limit, 100), 'fields' => 'paperId,title,authors,year,externalIds,url,abstract'], array_filter(['x-api-key' => $this->config['api_key'] ?? null]));
        return collect($json['data'] ?? [])->map(fn ($r) => $this->normalize($r['paperId'] ?? '', $r['title'] ?? '', $r['authors'] ?? [], $r['year'] ?? null, $r['externalIds']['DOI'] ?? null, $r['url'] ?? null, $r['abstract'] ?? null, $r))->all();
    }

    private function core(string $query, array $filters, int $limit): array
    {
        if (blank($this->config['api_key'] ?? null)) return [];
        $json = Http::timeout(20)->withHeaders(['Authorization' => 'Bearer '.$this->config['api_key']])->post(rtrim($this->config['base_url'], '/').'/search/works', ['q' => $query, 'limit' => $limit])->throw()->json();
        return collect($json['results'] ?? [])->map(fn ($r) => $this->normalize((string) ($r['id'] ?? ''), $r['title'] ?? '', $r['authors'] ?? [], $r['yearPublished'] ?? null, $r['doi'] ?? null, $r['downloadUrl'] ?? null, $r['abstract'] ?? null, $r))->all();
    }

    private function doaj(string $query, array $filters, int $limit): array
    {
        $json = $this->request(rtrim($this->config['base_url'], '/').'/search/articles/'.rawurlencode($query), ['pageSize' => $limit]);
        return collect($json['results'] ?? [])->map(fn ($r) => $this->normalize($r['id'] ?? '', $r['bibjson']['title'] ?? '', $r['bibjson']['author'] ?? [], $r['bibjson']['year'] ?? null, collect($r['bibjson']['identifier'] ?? [])->firstWhere('type', 'doi')['id'] ?? null, $r['bibjson']['link'][0]['url'] ?? null, $r['bibjson']['abstract'] ?? null, $r))->all();
    }

    private function pubmed(string $query, array $filters, int $limit): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $search = $this->request($base.'/esearch.fcgi', ['db' => 'pubmed', 'term' => $query, 'retmax' => $limit, 'retmode' => 'json']);
        $ids = $search['esearchresult']['idlist'] ?? [];
        if ($ids === []) return [];
        $summary = $this->request($base.'/esummary.fcgi', ['db' => 'pubmed', 'id' => implode(',', $ids), 'retmode' => 'json']);
        return collect($ids)->map(function ($id) use ($summary) {
            $r = $summary['result'][$id] ?? [];
            return $this->normalize((string) $id, $r['title'] ?? '', $r['authors'] ?? [], isset($r['pubdate']) ? (int) substr($r['pubdate'], 0, 4) : null, collect($r['articleids'] ?? [])->firstWhere('idtype', 'doi')['value'] ?? null, 'https://pubmed.ncbi.nlm.nih.gov/'.$id.'/', null, $r);
        })->all();
    }

    private function arxiv(string $query, array $filters, int $limit): array
    {
        $xml = $this->request(rtrim($this->config['base_url'], '/').'/api/query', ['search_query' => 'all:'.$query, 'start' => 0, 'max_results' => $limit]);
        $feed = @simplexml_load_string((string) $xml);
        if (! $feed) return [];
        return collect($feed->entry ?? [])->map(function ($entry) {
            $authors = [];
            foreach ($entry->author ?? [] as $author) $authors[] = ['name' => (string) $author->name];
            return $this->normalize((string) $entry->id, trim((string) $entry->title), $authors, (int) substr((string) $entry->published, 0, 4), null, (string) $entry->id, trim((string) $entry->summary), json_decode(json_encode($entry), true));
        })->all();
    }

    private function normalize(string $id, string $title, array $authors, ?int $year, ?string $doi, ?string $url, ?string $abstract, array $raw): array
    {
        return ['provider' => $this->provider, 'external_identifier' => $id, 'record_type' => 'work', 'title' => $title ?: 'Untitled scholarly work', 'authors' => $authors, 'publication_year' => $year, 'doi' => $doi ? preg_replace('#^https?://doi.org/#', '', $doi) : null, 'url' => $url, 'abstract' => $abstract, 'concepts' => [], 'raw_data' => $raw];
    }

    private function reconstructAbstract(array $inverted): ?string
    {
        if ($inverted === []) return null;
        $words = [];
        foreach ($inverted as $word => $positions) foreach ($positions as $position) $words[(int) $position] = $word;
        ksort($words);
        return implode(' ', $words);
    }
}
