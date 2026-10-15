<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResearchType extends Model
{
    protected $fillable = ['uuid', 'university_id', 'workflow_definition_id', 'name', 'slug', 'description', 'template_schema', 'validation_rules', 'similarity_threshold', 'publication_eligible', 'is_active'];
    protected function casts(): array { return ['template_schema' => 'array', 'validation_rules' => 'array', 'similarity_threshold' => 'decimal:2', 'publication_eligible' => 'boolean', 'is_active' => 'boolean']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function workflowDefinition(): BelongsTo { return $this->belongsTo(WorkflowDefinition::class); }
    public function projects(): HasMany { return $this->hasMany(ResearchProject::class); }
    public function templateVersions(): HasMany { return $this->hasMany(ResearchTemplateVersion::class)->orderByDesc('version'); }
    public function activeTemplateVersion(): ?ResearchTemplateVersion { return $this->templateVersions()->where('is_active', true)->first(); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
