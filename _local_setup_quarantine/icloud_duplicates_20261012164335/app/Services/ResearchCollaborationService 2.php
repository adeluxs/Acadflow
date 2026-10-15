<?php

namespace App\Services;

use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Models\ResearchSectionAuthorship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResearchCollaborationService
{
    public function recordEdit(ResearchSection $section, User $actor, string $before, string $after, ?int $contentVersionId = null): ResearchSectionAuthorship
    {
        $beforeWords = str_word_count(strip_tags($before));
        $afterWords = str_word_count(strip_tags($after));
        $wordsAdded = max(0, $afterWords - $beforeWords);
        $wordsRemoved = max(0, $beforeWords - $afterWords);
        $charactersAdded = max(0, mb_strlen($after) - mb_strlen($before));
        $charactersRemoved = max(0, mb_strlen($before) - mb_strlen($after));
        return ResearchSectionAuthorship::create([
            'research_section_id' => $section->id,
            'content_version_id' => $contentVersionId,
            'user_id' => $actor->id,
            'words_added' => $wordsAdded,
            'words_removed' => $wordsRemoved,
            'characters_added' => $charactersAdded,
            'characters_removed' => $charactersRemoved,
            'contribution_score' => ($wordsAdded * 1.0) + ($wordsRemoved * 0.25) + (($charactersAdded + $charactersRemoved) * 0.01),
            'metadata' => ['recorded_from_version_diff' => true],
            'created_at' => now(),
        ]);
    }

    public function syncMember(ResearchProject $project, User $user, string $role, array $permissions, ?float $declaredPercent = null): void
    {
        abort_unless($user->university_id === $project->university_id, 422, 'Project members must belong to the same institution.');
        $project->memberRecords()->updateOrCreate(['user_id' => $user->id], ['role' => $role, 'permissions' => array_values(array_unique($permissions)), 'contribution_percent' => $declaredPercent]);
    }

    public function contributionReport(ResearchProject $project): array
    {
        $project->loadMissing('memberRecords.user', 'sections.authorships');
        $scores = $project->sections->flatMap->authorships->groupBy('user_id')->map(fn ($items) => (float) $items->sum('contribution_score'));
        $total = max(1.0, (float) $scores->sum());
        return $project->memberRecords->map(function ($member) use ($scores, $total) {
            $score = (float) ($scores[$member->user_id] ?? 0);
            return [
                'user_id' => $member->user_id,
                'name' => $member->user?->full_name,
                'role' => $member->role,
                'measured_score' => round($score, 4),
                'measured_percent' => round($score / $total * 100, 2),
                'declared_percent' => $member->contribution_percent,
                'permissions' => $member->permissions,
            ];
        })->values()->all();
    }
}
