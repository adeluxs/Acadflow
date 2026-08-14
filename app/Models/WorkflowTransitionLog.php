<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['workflow_instance_id', 'from_stage_id', 'to_stage_id', 'actor_id', 'action', 'note', 'metadata', 'created_at'];
    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    public function instance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function fromStage(): BelongsTo { return $this->belongsTo(WorkflowStage::class, 'from_stage_id'); }
    public function toStage(): BelongsTo { return $this->belongsTo(WorkflowStage::class, 'to_stage_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
