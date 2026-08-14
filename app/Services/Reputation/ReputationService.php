<?php

namespace App\Services\Reputation;

use App\Models\Achievement;
use App\Models\KnowledgeCitation;
use App\Models\KnowledgeEvent;
use App\Models\KnowledgeFollow;
use App\Models\KnowledgePublication;
use App\Models\ReputationEvent;
use App\Models\ReputationProfile;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    public function recalculate(User $user): ReputationProfile
    {
        $publications = KnowledgePublication::query()->where('creator_id', $user->id)->where('status', 'published')->get();
        $publicationIds = $publications->pluck('id');
        $citations = KnowledgeCitation::query()->whereIn('cited_publication_id', $publicationIds)->whereColumn('citing_publication_id', '!=', 'cited_publication_id')->count();
        $followers = KnowledgeFollow::query()->where('target_type', 'creator')->where('target_id', $user->id)->count()
            + KnowledgeFollow::query()->where('target_type', User::class)->where('target_id', $user->id)->count();
        $engagement = KnowledgeEvent::query()->whereIn('knowledge_publication_id', $publicationIds)->selectRaw('event_type, COUNT(*) as aggregate')->groupBy('event_type')->pluck('aggregate', 'event_type');
        $approvedResearch = ResearchProject::query()->where('owner_id', $user->id)->whereNotNull('approved_at')->count();
        $eventPoints = (float) ReputationEvent::where('user_id', $user->id)->sum('points');

        $points = config('reputation.points');
        $weights = config('reputation.weights');
        $knowledgeScore = ($publications->count() * $points['publication']) + ($publications->sum('bookmark_count') * $points['bookmark']) + ((int) ($engagement['view'] ?? 0) * $points['view']);
        $qualityScore = ($citations * $points['citation']) + ($publications->whereNotNull('featured_at')->count() * $points['featured_publication']) + ($approvedResearch * $points['approved_research_quality']);
        $researchImpactScore = ($citations * $points['citation_research_impact']) + ($approvedResearch * $points['approved_research_impact']) + ($publications->where('content_type', 'research_output')->count() * $points['research_output']);
        $communityScore = ($followers * $points['follower']) + ((int) ($engagement['comment'] ?? 0) * $points['comment']) + ((int) ($engagement['reaction'] ?? 0) * $points['reaction']);
        $overall = max(0, round(($knowledgeScore * $weights['knowledge']) + ($qualityScore * $weights['quality']) + ($researchImpactScore * $weights['research_impact']) + ($communityScore * $weights['community']) + $eventPoints, 2));

        $profile = ReputationProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'university_id' => $user->university_id,
                'knowledge_score' => round($knowledgeScore, 2),
                'quality_score' => round($qualityScore, 2),
                'research_impact_score' => round($researchImpactScore, 2),
                'community_score' => round($communityScore, 2),
                'overall_score' => $overall,
                'level_key' => $this->levelFor($overall),
                'publication_count' => $publications->count(),
                'citation_count' => $citations,
                'follower_count' => $followers,
                'breakdown' => [
                    'views' => (int) ($engagement['view'] ?? 0),
                    'bookmarks' => $publications->sum('bookmark_count'),
                    'approved_research' => $approvedResearch,
                    'event_points' => $eventPoints,
                ],
                'calculated_at' => now(),
            ]
        );

        $this->awardEligibleAchievements($user, $profile);

        return $profile->fresh();
    }

    public function addEvent(User $user, string $eventType, float $points, mixed $source = null, array $metadata = []): ReputationEvent
    {
        return ReputationEvent::firstOrCreate([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'source_type' => is_object($source) ? $source::class : null,
            'source_id' => is_object($source) ? $source->getKey() : null,
        ], [
            'points' => $points,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function leaderboard(?int $universityId = null, int $limit = 50)
    {
        return ReputationProfile::query()
            ->with('user')
            ->when($universityId, fn ($query) => $query->where('university_id', $universityId))
            ->orderByDesc('overall_score')
            ->limit($limit)
            ->get();
    }

    private function awardEligibleAchievements(User $user, ReputationProfile $profile): void
    {
        Achievement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('university_id')->orWhere('university_id', $user->university_id))
            ->get()
            ->each(function (Achievement $achievement) use ($user, $profile): void {
                if ($this->meets($profile, $achievement->criteria ?? [])) {
                    DB::table('achievement_user')->updateOrInsert(
                        ['achievement_id' => $achievement->id, 'user_id' => $user->id],
                        ['evidence' => json_encode(['profile' => $profile->breakdown]), 'awarded_at' => now()]
                    );
                }
            });
    }

    private function meets(ReputationProfile $profile, array $criteria): bool
    {
        foreach ($criteria as $field => $minimum) {
            if ((float) data_get($profile, $field, 0) < (float) $minimum) {
                return false;
            }
        }
        return $criteria !== [];
    }

    private function levelFor(float $score): string
    {
        return collect(config('reputation.levels', []))
            ->sortByDesc('minimum')
            ->first(fn (array $level) => $score >= (float) ($level['minimum'] ?? 0))['key'] ?? 'new_contributor';
    }
}
