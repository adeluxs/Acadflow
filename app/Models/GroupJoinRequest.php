<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GroupJoinRequest extends Model
{
    protected $fillable = ['uuid','group_id','user_id','message','status','reviewed_by','reviewed_at'];
    protected function casts(): array { return ['reviewed_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function group() { return $this->belongsTo(Group::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
