<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerPosting extends Model
{
    protected $fillable=['ledger_journal_id','wallet_account_id','account_code','direction','amount_minor','currency','metadata'];
    protected function casts(): array { return ['amount_minor'=>'integer','metadata'=>'array']; }
    public function journal(){ return $this->belongsTo(LedgerJournal::class,'ledger_journal_id'); }
    public function wallet(){ return $this->belongsTo(WalletAccount::class,'wallet_account_id'); }
}
