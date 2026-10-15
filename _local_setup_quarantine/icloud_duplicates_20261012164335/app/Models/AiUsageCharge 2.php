<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class AiUsageCharge extends Model
{
    protected $fillable=['uuid','request_id','user_id','university_id','feature','provider','model','input_tokens','output_tokens','provider_cost_micro_usd','user_charge_minor','platform_margin_minor','currency','status','metadata'];
    protected function casts(): array { return ['input_tokens'=>'integer','output_tokens'=>'integer','provider_cost_micro_usd'=>'integer','user_charge_minor'=>'integer','platform_margin_minor'=>'integer','metadata'=>'array']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
}
