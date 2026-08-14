<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResearchProject extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid', 'university_id', 'department_id', 'academic_session_id', 'semester_id', 'research_type_id', 'research_template_version_id', 'owner_id', 'supervisor_id', 'co_supervisor_id', 'workflow_instance_id', 'title', 'research_area', 'keywords', 'abstract', 'status', 'specialization_type', 'progress', 'expected_completion_date', 'approved_at', 'archived_at', 'last_activity_at', 'metadata'];
    protected function casts(): array { return ['keywords' => 'array', 'progress' => 'decimal:2', 'expected_completion_date' => 'date', 'approved_at' => 'datetime', 'archived_at' => 'datetime', 'last_activity_at' => 'datetime', 'metadata' => 'array']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function researchType(): BelongsTo { return $this->belongsTo(ResearchType::class); }
    public function templateVersion(): BelongsTo { return $this->belongsTo(ResearchTemplateVersion::class, 'research_template_version_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function coSupervisor(): BelongsTo { return $this->belongsTo(User::class, 'co_supervisor_id'); }
    public function workflowInstance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class); }
    public function sections(): HasMany { return $this->hasMany(ResearchSection::class)->orderBy('position'); }
    public function corrections(): HasMany { return $this->hasMany(ResearchCorrection::class); }
    public function meetings(): HasMany { return $this->hasMany(ResearchMeeting::class); }
    public function milestones(): HasMany { return $this->hasMany(ResearchMilestone::class); }
    public function tasks(): HasMany { return $this->hasMany(ResearchTask::class); }
    public function archives(): HasMany { return $this->hasMany(ResearchArchive::class)->orderByDesc('version'); }
    public function amendments(): HasMany { return $this->hasMany(ResearchAmendment::class); }
    public function datasets(): HasMany { return $this->hasMany(ResearchDataset::class); }
    public function specializedLinks(): HasMany { return $this->hasMany(ResearchSpecializedLink::class); }
    public function siwesPlacement(): HasOne { return $this->hasOne(SiwesPlacement::class); }
    public function seminarSession(): HasOne { return $this->hasOne(SeminarSession::class); }
    public function literatureNotes(): HasMany { return $this->hasMany(ResearchLiteratureNote::class); }
    public function validationReports(): HasMany { return $this->hasMany(ResearchValidationReport::class)->latest(); }
    public function latestValidationReport(): HasOne { return $this->hasOne(ResearchValidationReport::class)->latestOfMany(); }
    public function memberRecords(): HasMany { return $this->hasMany(ResearchProjectMember::class); }
    public function members(): BelongsToMany { return $this->belongsToMany(User::class, 'research_project_members')->withPivot(['role', 'contribution_percent', 'permissions'])->withTimestamps(); }
    public function publications(): HasMany { return $this->hasMany(KnowledgePublication::class, 'source_research_project_id'); }
    public function referenceLinks(): HasMany { return $this->hasMany(AcademicReferenceLink::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
