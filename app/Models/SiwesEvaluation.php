<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiwesEvaluation extends Model
{
    protected $table = 'siwes_evaluations';
    protected $fillable = ['uuid','siwes_placement_id','evaluator_type','evaluator_id','attendance_score','technical_score','conduct_score','report_score','overall_score','comment','criteria','submitted_at'];
    protected function casts(): array { return ['criteria'=>'array','submitted_at'=>'datetime','attendance_score'=>'decimal:2','technical_score'=>'decimal:2','conduct_score'=>'decimal:2','report_score'=>'decimal:2','overall_score'=>'decimal:2']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function placement() { return $this->belongsTo(SiwesPlacement::class, 'siwes_placement_id'); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluator_id'); }
}
