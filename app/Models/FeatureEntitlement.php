<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class FeatureEntitlement extends Model
{
    protected $fillable=['uuid','user_id','feature','access_type','remaining_units','status','source_type','source_id','metadata','starts_at','expires_at'];
    protected function casts(): array { return ['remaining_units'=>'integer','metadata'=>'array','starts_at'=>'datetime','expires_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
    public function user(){ return $this->belongsTo(User::class); }
}
