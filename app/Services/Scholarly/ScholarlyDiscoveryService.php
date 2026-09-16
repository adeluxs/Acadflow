<?php

namespace App\Services\Scholarly;

use App\Models\ScholarlyRecord;
use App\Models\User;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ScholarlyDiscoveryService
{
    /** @return array<int,HttpScholarlyProvider> */
    private function providers(): array
    {
        return collect(config('scholarly.providers', []))
            ->map(fn ($configuration, $name) => new HttpScholarlyProvider($name, $configuration))
            ->values()
            ->all();
    }

    public function search(string $query, User $user, array $filters = [], int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $requestedProviders = collect($filters['providers'] ?? [])
            ->mapWithKeys(fn ($value, $key) => is_int($key) ? [(string) $value => true] : [(string) $key => (bool) $value])
            ->filter()
            ->all();
        $yearFrom = isset($filters['year_from']) && $filters['year_from'] !== '' ? (int) $filters['year_from'] : null;
        $yearTo = isset($filters['year_to']) && $filters['year_to'] !== '' ? (int) $filters['year_to'] : null;

        $providers = collect($this->providers())
            ->filter(fn (HttpScholarlyProvider $provider) => $provider->available())
            ->when($requestedProviders !== [], fn (Collection $items) => $items->filter(
                fn (HttpScholarlyProvider $provider) => isset($requestedProviders[$provider->name()])
            ))
            ->values();

        if ($providers->isEmpty()) {
            return [];
        }

        $cacheKey = 'scholarly:discover:'.sha1(json_encode([
            'q' => Str::lower(trim($query)),
            'providers' => $providers->map->name()->sort()->values()->all(),
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'limit' => $limit,
        ], JSON_UNESCAPED_UNICODE));

        $ttl = max(30, min((int) config('scholarly.search.cache_seconds', 300), 1800));

        /** @var array<int,array<string,mixed>>|null $discovered */
        $discovered = Cache::get($cacheKey);

        if (! is_array($discovered)) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($providers, $query, $filters, $limit): array {
                    return $providers
                        ->map(fn (HttpScholarlyProvider $provider) => $provider->enqueueSearch($pool, $query, $filters, min($limit, 20)))
                        ->filter()
                        ->values()
                        ->all();
                });
            } catch (Throwable $exception) {
                report($exception);
                $responses = [];
            }

            $discovered = $providers
                ->flatMap(function (HttpScholarlyProvider $provider) use ($responses, $filters, $limit): array {
                    try {
                        return $provider->parsePooledSearch($responses[$provider->name()] ?? null, $filters, min($limit, 20));
                    } catch (Throwable $exception) {
                        report($exception);
                        return [];
                    }
                })
                ->values()
                ->all();

            // Do not cache a total outage / empty provider response. A transient
            // network problem should be retryable immediately on the next search.
            if ($discovered !== []) {
                Cache::put($cacheKey, $discovered, now()->addSeconds($ttl));
            }
        }

        $records = collect($discovered)
            ->filter(function (array $record) use ($yearFrom, $yearTo): bool {
                $year = isset($record['publication_year']) ? (int) $record['publication_year'] : null;
                if ($yearFrom && $year && $year < $yearFrom) {
                    return false;
                }
                if ($yearTo && $year && $year > $yearTo) {
                    return false;
                }

                return true;
            })
            ->unique(function (array $record): string {
                $doi = Str::lower(trim((string) ($record['doi'] ?? '')));
                if ($doi !== '') {
                    return 'doi:'.$doi;
                }

                $title = Str::lower((string) preg_replace('/[^\pL\pN]+/u', ' ', (string) ($record['title'] ?? '')));

                return 'title:'.trim($title).'|'.((string) ($record['publication_year'] ?? ''));
            })
            ->take($limit)
            ->values();

        if ($records->isEmpty()) {
            return [];
        }

        return $this->persistBatch($records, $user);
    }

    /**
     * Persist discovery results in small batch upserts. Existing record UUIDs are
     * intentionally not updated, so saved project references keep stable links.
     * This replaces up to 50 updateOrCreate() round trips with at most two writes
     * plus one read query.
     *
     * @param Collection<int,array<string,mixed>> $records
     * @return array<int,array<string,mixed>>
     */
    private function persistBatch(Collection $records, User $user): array
    {
        $records = $records->map(function (array $record): array {
            if (blank($record['external_identifier'] ?? null)) {
                $record['external_identifier'] = 'fallback:'.sha1(implode('|', [
                    (string) ($record['provider'] ?? 'unknown'),
                    Str::lower(trim((string) ($record['title'] ?? ''))),
                    (string) ($record['publication_year'] ?? ''),
                    Str::lower(trim((string) ($record['doi'] ?? ''))),
                ]));
            }

            return $record;
        })->values();

        $now = now();
        $rows = $records->map(function (array $record) use ($user, $now): array {
            return [
                'uuid' => (string) Str::uuid(),
                'university_id' => $user->university_id,
                'provider' => (string) ($record['provider'] ?? 'unknown'),
                'external_identifier' => (string) $record['external_identifier'],
                'record_type' => (string) ($record['record_type'] ?? 'work'),
                'title' => (string) ($record['title'] ?? 'Untitled scholarly work'),
                'authors' => $this->json($record['authors'] ?? []),
                'publication_year' => isset($record['publication_year']) ? (int) $record['publication_year'] : null,
                'doi' => filled($record['doi'] ?? null) ? (string) $record['doi'] : null,
                'orcid' => filled($record['orcid'] ?? null) ? (string) $record['orcid'] : null,
                'url' => filled($record['url'] ?? null) ? (string) $record['url'] : null,
                'abstract' => filled($record['abstract'] ?? null) ? (string) $record['abstract'] : null,
                'concepts' => $this->json($record['concepts'] ?? []),
                'raw_data' => $this->json($record['raw_data'] ?? []),
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        $updateColumns = [
            'record_type',
            'title',
            'authors',
            'publication_year',
            'doi',
            'orcid',
            'url',
            'abstract',
            'concepts',
            'raw_data',
            'fetched_at',
            'updated_at',
        ];

        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('scholarly_records')->upsert(
                $chunk,
                ['provider', 'external_identifier'],
                $updateColumns,
            );
        }

        $grouped = $records->groupBy(fn (array $record): string => (string) ($record['provider'] ?? 'unknown'));
        $query = ScholarlyRecord::query()->where(function ($outer) use ($grouped): void {
            foreach ($grouped as $provider => $providerRecords) {
                $identifiers = $providerRecords
                    ->pluck('external_identifier')
                    ->filter(fn ($value) => filled($value))
                    ->map(fn ($value) => (string) $value)
                    ->unique()
                    ->values()
                    ->all();

                if ($identifiers === []) {
                    continue;
                }

                $outer->orWhere(function ($nested) use ($provider, $identifiers): void {
                    $nested->where('provider', $provider)->whereIn('external_identifier', $identifiers);
                });
            }
        });

        $stored = $query->get()->keyBy(fn (ScholarlyRecord $record): string => $record->provider.'|'.$record->external_identifier);

        return $records
            ->map(function (array $record) use ($stored): array {
                $key = (string) ($record['provider'] ?? 'unknown').'|'.(string) ($record['external_identifier'] ?? '');

                return $stored->get($key)?->toArray() ?? $record;
            })
            ->values()
            ->all();
    }

    private function json(mixed $value): string
    {
        return (string) (json_encode($value ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]');
    }
}
