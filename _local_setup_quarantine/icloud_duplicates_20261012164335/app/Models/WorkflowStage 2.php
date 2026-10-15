<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    protected $fillable = ['workflow_definition_id', 'key', 'name', 'position', 'deadline_days', 'actor_roles', 'settings', 'requirements', 'is_initial', 'is_final'];

    protected function casts(): array
    {
        return ['deadline_days' => 'integer', 'actor_roles' => 'array', 'settings' => 'array', 'requirements' => 'array', 'is_initial' => 'boolean', 'is_final' => 'boolean'];
    }

    public function definition(): BelongsTo { return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id'); }
    public function instances(): HasMany { return $this->hasMany(WorkflowInstance::class, 'current_stage_id'); }
}
