<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiwesAttendanceRecord extends Model
{
    protected $table = 'siwes_attendance_records';
    protected $fillable = ['uuid','siwes_placement_id','attendance_date','check_in_at','check_out_at','hours_worked','status','latitude','longitude','verified_by_type','verified_by','note'];
    protected function casts(): array { return ['attendance_date'=>'date','hours_worked'=>'decimal:2','latitude'=>'decimal:7','longitude'=>'decimal:7']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function placement() { return $this->belongsTo(SiwesPlacement::class, 'siwes_placement_id'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
