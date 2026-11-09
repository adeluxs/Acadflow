<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges Knowledge Hub categories that exist more than once: platform-wide rows with the
 * same name, and the per-university copies the starter setup made for every university.
 * One category per name is kept (made platform-wide); content moves onto it and the copies
 * are switched off and marked as merged. Nothing is deleted.
 */
class KnowledgeCategoryMerger
{
    /** Tables that point at a category. */
    private const REFERENCES = [
        ['knowledge_publications', 'category_id'],
        ['knowledge_series', 'category_id'],
        ['knowledge_communities', 'category_id'],
        ['academic_events', 'category_id'],
        ['academic_challenges', 'category_id'],
        ['knowledge_writing_entries', 'track_category_id'],
        ['knowledge_categories', 'parent_id'],
    ];

    /** @return Collection<int,array{keep:KnowledgeCategory,extras:Collection<int,KnowledgeCategory>}> */
    public function plan(): Collection
    {
        return KnowledgeCategory::query()->whereNull('merged_into_id')->orderBy('id')->get()
            ->groupBy(fn (KnowledgeCategory $c) => ($c->parent_id ?? 0).'|'.KnowledgeCategory::nameKey($c->name))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(function ($group) {
                $keep = $group->sortBy(fn (KnowledgeCategory $c) => [$c->university_id === null ? 0 : 1, $c->is_active ? 0 : 1, $c->id])->first();

                return ['keep' => $keep, 'extras' => $group->reject(fn (KnowledgeCategory $c) => $c->id === $keep->id)->values()];
            })->values();
    }

    public function duplicateCount(): int
    {
        return $this->plan()->sum(fn ($row) => $row['extras']->count());
    }

    /** Merges every duplicate; returns how many copies were merged. */
    public function merge(): int
    {
        $merged = 0;
        // Children of merged parents only become identical after their parents merge, so repeat.
        for ($pass = 0; $pass < 3; $pass++) {
            $plan = $this->plan();
            if ($plan->isEmpty()) {
                break;
            }
            foreach ($plan as ['keep' => $keep, 'extras' => $extras]) {
                DB::transaction(fn () => $this->mergeGroup($keep, $extras));
                $merged += $extras->count();
            }
        }

        return $merged;
    }

    /** @param Collection<int,KnowledgeCategory> $extras */
    private function mergeGroup(KnowledgeCategory $keep, Collection $extras): void
    {
        $ids = $extras->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (self::REFERENCES as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->whereIn($column, $ids)->when($table === 'knowledge_categories', fn ($q) => $q->whereNotIn('id', [$keep->id]))->update([$column => $keep->id]);
            }
        }
        if (Schema::hasTable('knowledge_writing_challenges')) {
            foreach (DB::table('knowledge_writing_challenges')->whereNotNull('tracks')->get(['id', 'tracks']) as $challenge) {
                $tracks = array_map('intval', (array) json_decode((string) $challenge->tracks, true));
                $mapped = array_values(array_unique(array_map(fn ($id) => in_array($id, $ids, true) ? (int) $keep->id : $id, $tracks)));
                if ($mapped !== $tracks) {
                    DB::table('knowledge_writing_challenges')->where('id', $challenge->id)->update(['tracks' => json_encode($mapped)]);
                }
            }
        }
        DB::table('knowledge_categories')->whereIn('id', $ids)->update(['is_active' => false, 'merged_into_id' => $keep->id, 'updated_at' => now()]);

        $updates = [];
        if (! $keep->is_active && $extras->contains('is_active', true)) {
            $updates['is_active'] = true;
        }
        // The same name existed at several universities: make the kept one platform-wide,
        // unless a different platform-wide category already uses its slug.
        if ($keep->university_id !== null && ! KnowledgeCategory::query()->whereNull('university_id')->whereNull('merged_into_id')->where('slug', $keep->slug)->exists()) {
            $updates['university_id'] = null;
        }
        if ($updates) {
            $keep->forceFill($updates)->save();
        }
    }
}
