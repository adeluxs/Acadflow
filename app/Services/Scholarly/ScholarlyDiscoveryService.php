<?php

namespace App\Services\Scholarly;

use App\Models\ScholarlyRecord;
use App\Models\User;

class ScholarlyDiscoveryService
{
    /** @return array<int,HttpScholarlyProvider> */
    private function providers(): array
    {
        return collect(config('scholarly.providers', []))
            ->map(fn ($configuration, $name) => new HttpScholarlyProvider($name, $configuration))
            ->values()->all();
    }

    public function search(string $query, User $user, array $filters = [], int $limit = 20): array
    {
        $records = collect();
        foreach ($this->providers() as $provider) {
            if (! $provider->available() || ! ($filters['providers'][$provider->name()] ?? true)) continue;
            try {
                $records = $records->concat($provider->search($query, $filters, min($limit, 20)));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $records->unique(fn ($r) => strtolower((string) ($r['doi'] ?? $r['provider'].'|'.$r['external_identifier'])))
            ->take($limit)->map(function (array $record) use ($user) {
                $stored = ScholarlyRecord::updateOrCreate(
                    ['provider' => $record['provider'], 'external_identifier' => $record['external_identifier']],
                    $record + ['university_id' => $user->university_id, 'fetched_at' => now()]
                );
                return $stored->toArray();
            })->values()->all();
    }
}
