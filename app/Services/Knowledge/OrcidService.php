<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OrcidService
{
    public function sync(User $user): CreatorProfile
    {
        $profile = $user->creatorProfile()->firstOrFail();
        $orcid = trim((string) $profile->orcid);
        if (! preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            throw ValidationException::withMessages(['orcid' => 'Save a valid ORCID iD before synchronizing.']);
        }

        $base = rtrim((string) config('scholarly.orcid.base_url', 'https://pub.orcid.org/v3.0'), '/');
        $response = Http::accept('application/json')->timeout(15)->retry(2, 300)->get($base.'/'.$orcid.'/record');
        if (! $response->successful()) {
            throw ValidationException::withMessages(['orcid' => 'The public ORCID record could not be retrieved.']);
        }

        $record = $response->json();
        $person = $record['person'] ?? [];
        $activities = $record['activities-summary'] ?? [];
        $external = $profile->external_profiles ?? [];
        $external['orcid'] = [
            'orcid' => $orcid,
            'name' => trim(implode(' ', array_filter([
                data_get($person, 'name.given-names.value'),
                data_get($person, 'name.family-name.value'),
            ]))),
            'biography' => data_get($person, 'biography.content'),
            'keywords' => collect(data_get($person, 'keywords.keyword', []))->pluck('content')->filter()->values()->all(),
            'researcher_urls' => collect(data_get($person, 'researcher-urls.researcher-url', []))->map(fn ($url) => [
                'name' => data_get($url, 'url-name'),
                'url' => data_get($url, 'url.value'),
            ])->filter(fn ($url) => $url['url'])->values()->all(),
            'works_count' => count((array) data_get($activities, 'works.group', [])),
            'source' => 'ORCID Public API',
            'synced_at' => now()->toIso8601String(),
        ];

        $profile->update(['external_profiles' => $external, 'orcid_synced_at' => now()]);

        return $profile->fresh();
    }
}
