<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchCorrection extends Model
{
    protected $fillable = ['uuid', 'research_project_id', 'research_section_id', 'requested_by', 'assigned_to', 'type', 'description', 'status', 'due_at', 'resolved_at'];
    protected function casts(): array { return ['due_at' => 'datetime', 'resolved_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function section(): BelongsTo { return $this->belongsTo(ResearchSection::class, 'research_section_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
