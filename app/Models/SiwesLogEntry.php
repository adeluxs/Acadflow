<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SiwesLogEntry extends Model
{
    use SoftDeletes;
    protected $table = 'siwes_log_entries';
    protected $fillable = ['uuid','siwes_placement_id','created_by','entry_date','period_type','hours','title','activities','skills_learned','challenges','status','employer_comment','lecturer_comment','reviewed_by','reviewed_at'];
    protected function casts(): array { return ['entry_date'=>'date','hours'=>'integer','reviewed_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function placement() { return $this->belongsTo(SiwesPlacement::class, 'siwes_placement_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
