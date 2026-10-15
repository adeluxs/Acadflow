<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MonetizationIdempotencyKey extends Model
{
    protected $fillable=['user_id','operation','idempotency_key','request_hash','status','result_type','result_id','response','expires_at'];
    protected function casts(): array { return ['response'=>'array','expires_at'=>'datetime']; }
}
