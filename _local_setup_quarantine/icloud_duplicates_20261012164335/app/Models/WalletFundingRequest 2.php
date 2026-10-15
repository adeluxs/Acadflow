<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class WalletFundingRequest extends Model
{
    protected $fillable=['uuid','user_id','wallet_account_id','transaction_id','amount_minor','currency','status','gateway_reference','idempotency_key','metadata','verified_at'];
    protected function casts(): array { return ['amount_minor'=>'integer','metadata'=>'array','verified_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
    public function user(){ return $this->belongsTo(User::class); }
    public function wallet(){ return $this->belongsTo(WalletAccount::class,'wallet_account_id'); }
    public function transaction(){ return $this->belongsTo(Transaction::class); }
}
