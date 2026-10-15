<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryEvent;
use App\Models\KnowledgePublication;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function record(User $user, KnowledgePublication $publication, string $eventType, float $weight = 1.0, array $metadata = []): void
    {
        DiscoveryEvent::create([
            'user_id' => $user->id,
            'university_id' => $user->university_id,
            'target_type' => KnowledgePublication::class,
            'target_id' => $publication->id,
            'event_type' => $eventType,
            'weight' => $weight,
            'privacy_scope' => 'private',
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function refresh(User $user, int $limit = 50): Collection
    {
        $personalized = data_get($user->creatorProfile?->privacy_settings, 'personalized_recommendations', true);
        if ($personalized === false) {
            Recommendation::where('user_id', $user->id)->delete();
            return $this->trending($user, $limit);
        }
        $events = DiscoveryEvent::query()
            ->where('user_id', $user->id)
            ->where('target_type', KnowledgePublication::class)
            ->latest('created_at')
            ->limit(500)
            ->get();

        $seenIds = $events->pluck('target_id')->unique();
        $interestPublications = KnowledgePublication::query()->with('tags')->whereIn('id', $seenIds)->get();
        $tagIds = $interestPublications->flatMap->tags->pluck('id')->countBy()->sortDesc()->keys()->take(20);
        $categoryIds = $interestPublications->pluck('category_id')->filter()->countBy()->sortDesc()->keys()->take(10);

        $candidates = KnowledgePublication::query()
            ->with(['tags', 'creator'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotIn('id', $seenIds)
            ->where(function ($visibility) use ($user): void {
                $visibility->where('visibility', 'public')
                    ->orWhere(fn ($institution) => $institution->where('visibility', 'institution')->where('university_id', $user->university_id));
            })
            ->limit(300)
            ->get();

        Recommendation::where('user_id', $user->id)->delete();
        $ranked = $candidates->map(function (KnowledgePublication $publication) use ($user, $tagIds, $categoryIds): array {
            $tagOverlap = $publication->tags->pluck('id')->intersect($tagIds)->count();
            $categoryMatch = $categoryIds->contains($publication->category_id) ? 1 : 0;
            $departmentMatch = $publication->department_id && $publication->department_id === $user->department_id ? 1 : 0;
            $institutionMatch = $publication->university_id && $publication->university_id === $user->university_id ? 1 : 0;
            $quality = min(1, log10(max(1, $publication->view_count + $publication->bookmark_count * 3)) / 5);
            $score = ($tagOverlap * 0.25) + ($categoryMatch * 0.35) + ($departmentMatch * 0.3) + ($institutionMatch * 0.15) + ($quality * 0.25);
            return ['publication' => $publication, 'score' => round($score, 4), 'signals' => compact('tagOverlap', 'categoryMatch', 'departmentMatch', 'institutionMatch', 'quality')];
        })->sortByDesc('score')->take($limit)->values();

        foreach ($ranked as $rank) {
            Recommendation::create([
                'user_id' => $user->id,
                'target_type' => KnowledgePublication::class,
                'target_id' => $rank['publication']->id,
                'score' => $rank['score'],
                'reason' => $this->reason($rank['signals']),
                'signals' => $rank['signals'],
                'expires_at' => now()->addDay(),
            ]);
        }

        return $ranked;
    }

    public function forUser(User $user, int $limit = 12): Collection
    {
        if (data_get($user->creatorProfile?->privacy_settings, 'personalized_recommendations', true) === false) {
            return $this->trending($user, $limit)->map(fn (array $item) => [
                'recommendation' => (object) ['reason' => 'Popular academic resource (personalization disabled)'],
                'publication' => $item['publication'],
            ]);
        }

        $records = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('target_type', KnowledgePublication::class)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('score')
            ->limit($limit)
            ->get();

        if ($records->isEmpty()) {
            $this->refresh($user);
            $records = Recommendation::query()->where('user_id', $user->id)->where('target_type', KnowledgePublication::class)->orderByDesc('score')->limit($limit)->get();
        }

        $publications = KnowledgePublication::with(['creator', 'category', 'tags'])->whereIn('id', $records->pluck('target_id'))->get()->keyBy('id');
        return $records->map(fn (Recommendation $recommendation) => ['recommendation' => $recommendation, 'publication' => $publications->get($recommendation->target_id)])->filter(fn ($item) => $item['publication'])->values();
    }

    private function trending(User $user, int $limit): Collection
    {
        return KnowledgePublication::query()
            ->with(['creator', 'category', 'tags'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($visibility) use ($user): void {
                $visibility->where('visibility', 'public')
                    ->orWhere(fn ($institution) => $institution->where('visibility', 'institution')->where('university_id', $user->university_id));
            })
            ->orderByRaw('(view_count + bookmark_count * 3 + citation_count * 5) DESC')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgePublication $publication) => [
                'publication' => $publication,
                'score' => 0,
                'signals' => ['privacy_mode' => 'non_personalized'],
            ]);
    }

    private function reason(array $signals): string
    {
        return match (true) {
            $signals['departmentMatch'] === 1 => 'Relevant to your department',
            $signals['tagOverlap'] > 0 => 'Matches topics you read',
            $signals['categoryMatch'] === 1 => 'Related to your preferred categories',
            $signals['institutionMatch'] === 1 => 'Popular in your institution',
            default => 'Recommended academic resource',
        };
    }
}
