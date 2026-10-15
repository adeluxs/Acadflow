<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class PricingRule extends Model
{
    protected $fillable=['uuid','key','name','scope_type','scope_id','version','supersedes_id','currency','unit_amount_minor','percentage_basis_points','enabled','metadata','starts_at','ends_at'];
    protected function casts(): array { return ['version'=>'integer','supersedes_id'=>'integer','unit_amount_minor'=>'integer','percentage_basis_points'=>'integer','enabled'=>'boolean','metadata'=>'array','starts_at'=>'datetime','ends_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
}
