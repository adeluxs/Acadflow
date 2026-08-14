<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchValidationReport extends Model
{
    protected $fillable = ['uuid', 'research_project_id', 'requested_by', 'ai_analysis_id', 'status', 'readiness_score', 'similarity_score', 'source', 'summary', 'findings', 'completed_at'];
    protected function casts(): array { return ['readiness_score' => 'decimal:2', 'similarity_score' => 'decimal:2', 'findings' => 'array', 'completed_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function aiAnalysis(): BelongsTo { return $this->belongsTo(AiAnalysis::class); }
}
