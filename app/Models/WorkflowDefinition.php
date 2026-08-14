<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowDefinition extends Model
{
    protected $fillable = ['uuid', 'university_id', 'key', 'name', 'subject_type', 'description', 'settings', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function stages(): HasMany { return $this->hasMany(WorkflowStage::class)->orderBy('position'); }
    public function instances(): HasMany { return $this->hasMany(WorkflowInstance::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
