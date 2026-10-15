<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiwesPlacement extends Model
{
    protected $table = 'siwes_placements';
    protected $fillable = ['uuid','research_project_id','submission_id','student_id','organization_name','organization_address','industry_sector','industry_supervisor_name','industry_supervisor_email','industry_supervisor_phone','started_on','ended_on','required_hours','completed_hours','status','metadata'];
    protected function casts(): array { return ['started_on'=>'date','ended_on'=>'date','required_hours'=>'integer','completed_hours'=>'integer','metadata'=>'array']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function project() { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function submission() { return $this->belongsTo(Submission::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function logs() { return $this->hasMany(SiwesLogEntry::class); }
    public function attendance() { return $this->hasMany(SiwesAttendanceRecord::class); }
    public function evaluations() { return $this->hasMany(SiwesEvaluation::class); }
}
