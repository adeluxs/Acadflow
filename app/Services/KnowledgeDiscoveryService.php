<?php

namespace App\Services;

use App\Models\KnowledgeEvent;
use App\Models\KnowledgePublication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KnowledgeDiscoveryService
{
    public function search(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = KnowledgePublication::query()
            ->with(['creator', 'category', 'tags', 'university', 'department',
                // Cards need a short fallback preview, not complete manuscripts.
                'document' => fn ($q) => $q->select('id')->selectRaw('SUBSTR(body, 1, 2048) AS body'),
            ])
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

        if ($user) {
            $query->where(function ($access) use ($user) {
                $access->where('access_type', '!=', 'institution')
                    ->orWhere(function ($institution) use ($user) {
                        $institution->where('access_type', 'institution')
                            ->where('university_id', $user->university_id);
                    });
            });

        }
        if (! (bool) SettingService::getGlobal('knowledge_paid_content_enabled', true)) {
            $query->where('access_type', '!=', 'premium');
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
            $query->where(fn ($typeQuery) => $typeQuery->where('content_type', $type)->orWhereHas('publicationType', fn ($publicationType) => $publicationType->where('slug', $type)));
        }
        if ($area = $filters['area'] ?? null) {
            $query->whereHas('publicationType', fn ($typeQuery) => $typeQuery->where('area', $area));
        }
        if ($access = $filters['access'] ?? null) {
            $query->where('access_type', $access === 'paid' ? 'premium' : $access);
        }
        if (($filters['recency'] ?? null) === 'week') {
            $query->where('published_at', '>=', now()->subWeek());
        }
        if (($filters['recency'] ?? null) === 'month') {
            $query->where('published_at', '>=', now()->subMonth());
        }
        if ($user && ($filters['feed'] ?? null) === 'saved') {
            $query->whereHas('bookmarks', fn ($bookmark) => $bookmark->where('user_id', $user->id));
        }
        if ($user && ($filters['feed'] ?? null) === 'mine') {
            $query->where('creator_id', $user->id);
        }
        if ($user && ($filters['feed'] ?? null) === 'following') {
            $query->whereExists(function ($subscription) use ($user) {
                $subscription->selectRaw('1')->from('engagement_subscriptions')->whereColumn('engagement_subscriptions.subscribable_id', 'knowledge_publications.id')->where('engagement_subscriptions.subscribable_type', KnowledgePublication::class)->where('engagement_subscriptions.user_id', $user->id)->where('engagement_subscriptions.is_muted', false);
            });
        }

        if (($filters['sort'] ?? null) === 'popular') {
            $query->orderByDesc('bookmark_count')->orderByDesc('view_count');
        } else {
            $query->orderByDesc('published_at');
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Counts a read once per person: once per signed-in account, and once per browser for
     * visitors who are not signed in. Refreshing, saving, following or coming back later
     * does not add to the count. A visitor who later signs in is not counted a second time.
     */
    public function recordView(KnowledgePublication $publication, ?User $user, array $metadata = [], ?string $visitorKey = null): bool
    {
        if (! $publication->isPublished() || (! $user && ! $visitorKey)) {
            return false;
        }
        $visitorHash = $visitorKey ? hash('sha256', $visitorKey) : null;
        $reads = DB::table('knowledge_publication_reads')->where('knowledge_publication_id', $publication->id);

        if ($user) {
            if ((clone $reads)->where('user_id', $user->id)->exists()) {
                return false;
            }
            // Read before signing in on this browser: link that read to the account instead of counting again.
            if ($visitorHash && (clone $reads)->whereNull('user_id')->where('visitor_key', $visitorHash)->update(['user_id' => $user->id]) > 0) {
                return false;
            }
            $inserted = DB::table('knowledge_publication_reads')->insertOrIgnore(['knowledge_publication_id' => $publication->id, 'user_id' => $user->id, 'visitor_key' => null, 'created_at' => now()]);
        } else {
            $inserted = DB::table('knowledge_publication_reads')->insertOrIgnore(['knowledge_publication_id' => $publication->id, 'user_id' => null, 'visitor_key' => $visitorHash, 'created_at' => now()]);
        }
        if ($inserted < 1) {
            return false;
        }

        // Raw increment: no model events and no updated_at change, so a read never looks like an edit.
        DB::table('knowledge_publications')->where('id', $publication->id)->increment('view_count');
        $publication->view_count = (int) $publication->view_count + 1;
        $publication->syncOriginalAttribute('view_count');
        KnowledgeEvent::create([
            'knowledge_publication_id' => $publication->id,
            'user_id' => $user?->id,
            'event_type' => 'view',
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        return true;
    }
}
