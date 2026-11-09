<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    protected $fillable = ['university_id', 'parent_id', 'merged_into_id', 'name', 'slug', 'description', 'area', 'icon', 'allowed_publication_types', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['allowed_publication_types' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(KnowledgePublication::class, 'category_id');
    }

    /** Platform-wide categories plus the ones belonging to this university. */
    public function scopeVisibleTo(Builder $query, ?int $universityId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('university_id')->when($universityId, fn (Builder $own) => $own->orWhere('university_id', $universityId)));
    }

    /** Lower-cased, single-spaced name used to spot the same category stored twice. */
    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', str_replace('&', 'and', $name))));
    }

    /**
     * Categories for dropdowns and filters: active, visible to the university, and each name
     * shown once (the platform-wide row wins over a university copy, then the oldest row).
     *
     * @return Collection<int,self>
     */
    public static function picker(?int $universityId, bool $withParent = false): Collection
    {
        $rows = self::query()->when($withParent, fn (Builder $q) => $q->with('parent:id,name'))
            ->where('is_active', true)->whereNull('merged_into_id')->visibleTo($universityId)
            ->orderBy('sort_order')->orderBy('name')->get();

        $keep = $rows->groupBy(fn (self $c) => self::nameKey(($c->parent?->name ? $c->parent->name.' / ' : '').$c->name))
            ->map(fn ($group) => $group->sortBy(fn (self $c) => [$c->university_id === null ? 0 : 1, $c->id])->first()->id)
            ->flip();

        return $rows->filter(fn (self $c) => $keep->has($c->id))->values();
    }
}
