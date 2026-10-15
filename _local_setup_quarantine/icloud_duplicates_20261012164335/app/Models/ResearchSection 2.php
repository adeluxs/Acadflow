<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchSection extends Model
{
    protected $fillable = ['uuid', 'research_project_id', 'content_document_id', 'created_by', 'key', 'title', 'position', 'is_required', 'status', 'completion_percent', 'approved_by', 'approved_at', 'locked_by', 'locked_at'];
    protected function casts(): array { return ['is_required' => 'boolean', 'completion_percent' => 'decimal:2', 'approved_at' => 'datetime', 'locked_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function document(): BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function locker(): BelongsTo { return $this->belongsTo(User::class, 'locked_by'); }
    public function authorships(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ResearchSectionAuthorship::class); }
    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ResearchTask::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
