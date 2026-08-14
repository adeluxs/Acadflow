<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeminarSession extends Model
{
    protected $table = 'seminar_sessions';
    protected $fillable = ['uuid','research_project_id','submission_id','scheduled_by','title','scheduled_at','duration_minutes','venue','online_url','slide_media_asset_id','status','moderator_notes','final_score','completed_at'];
    protected function casts(): array { return ['scheduled_at'=>'datetime','completed_at'=>'datetime','duration_minutes'=>'integer','final_score'=>'decimal:2']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function project() { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function submission() { return $this->belongsTo(Submission::class); }
    public function scheduler() { return $this->belongsTo(User::class, 'scheduled_by'); }
    public function slideAsset() { return $this->belongsTo(MediaAsset::class, 'slide_media_asset_id'); }
    public function panelMembers() { return $this->hasMany(SeminarPanelMember::class); }
    public function questions() { return $this->hasMany(SeminarQuestion::class); }
}
