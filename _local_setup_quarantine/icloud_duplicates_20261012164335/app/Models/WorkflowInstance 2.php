<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowInstance extends Model
{
    protected $fillable = ['uuid', 'workflow_definition_id', 'subject_type', 'subject_id', 'current_stage_id', 'started_by', 'status', 'context', 'completed_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function definition(): BelongsTo { return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id'); }
    public function currentStage(): BelongsTo { return $this->belongsTo(WorkflowStage::class, 'current_stage_id'); }
    public function starter(): BelongsTo { return $this->belongsTo(User::class, 'started_by'); }
    public function transitions(): HasMany { return $this->hasMany(WorkflowTransitionLog::class)->orderBy('created_at'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
