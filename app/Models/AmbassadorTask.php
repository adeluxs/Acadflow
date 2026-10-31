<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** An admin-published programme activity that approved ambassadors can report against. */
class AmbassadorTask extends Model
{
    protected $guarded = ['id', 'uuid', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'requires_evidence' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $task) => $task->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function reports() { return $this->hasMany(AmbassadorReport::class, 'ambassador_task_id'); }

    /** Active and inside its optional date window (dates are inclusive, app timezone). */
    public function scopeAvailable(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
    }
}
