<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GroupInvitation extends Model
{
    protected $fillable = ['uuid','group_id','inviter_id','invitee_id','email','role','status','token_hash','expires_at','responded_at'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['expires_at'=>'datetime','responded_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function group() { return $this->belongsTo(Group::class); }
    public function inviter() { return $this->belongsTo(User::class, 'inviter_id'); }
    public function invitee() { return $this->belongsTo(User::class, 'invitee_id'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
