<?php

namespace App\Services;

use App\Models\KnowledgeEvent;
use App\Models\KnowledgePublication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KnowledgeDiscoveryService
{
    public function search(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = KnowledgePublication::query()
            ->with(['creator', 'category', 'tags', 'university', 'department', 'document'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $query->where(function ($visibility) use ($user) {
            $visibility->where('visibility', 'public');
            if ($user?->university_id) {
                $visibility->orWhere(function ($institution) use ($user) {
                    $institution->where('visibility', 'institution')
                        ->where('university_id', $user->university_id);
                });
            }
            if ($user) {
                $visibility->orWhere('creator_id', $user->id);
            }
        });


        if (! $user) {
            $query->where('access_type', 'free');
        } else {
            $query->where(function ($access) use ($user) {
                $access->where('access_type', '!=', 'institution')
                    ->orWhere(function ($institution) use ($user) {
                        $institution->where('access_type', 'institution')
                            ->where('university_id', $user->university_id);
                    });
            });

            if (! FeatureAccessService::canAccessFeature('knowledge_hub_premium', $user)
                || ! $user->hasFeature('knowledge_hub_premium')) {
                $query->where('access_type', '!=', 'premium');
            }
        }

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($search) use ($term) {
                $search->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%")
                    ->orWhereHas('document', fn ($document) => $document->where('body', 'like', "%{$term}%"));
            });
        }

        if ($category = $filters['category'] ?? null) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }
        if ($type = $filters['type'] ?? null) {
            $query->where('content_type', $type);
        }
        if ($access = $filters['access'] ?? null) {
            $query->where('access_type', $access);
        }

        return $query->orderByDesc('published_at')->paginate(15)->withQueryString();
    }

    public function recordView(KnowledgePublication $publication, ?User $user, array $metadata = []): void
    {
        $publication->increment('view_count');
        KnowledgeEvent::create([
            'knowledge_publication_id' => $publication->id,
            'user_id' => $user?->id,
            'event_type' => 'view',
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
