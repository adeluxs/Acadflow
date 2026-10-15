<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LedgerJournal extends Model
{
    protected $fillable = ['uuid','reference','operation','user_id','currency','status','metadata','posted_at'];
    protected function casts(): array { return ['metadata'=>'array','posted_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
    public function postings(){ return $this->hasMany(LedgerPosting::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
