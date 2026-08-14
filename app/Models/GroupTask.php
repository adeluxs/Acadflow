<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GroupTask extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid','group_id','creator_id','assignee_id','title','description','status','priority','due_at','completed_at'];
    protected function casts(): array { return ['due_at'=>'datetime','completed_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function group() { return $this->belongsTo(Group::class); }
    public function creator() { return $this->belongsTo(User::class, 'creator_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
