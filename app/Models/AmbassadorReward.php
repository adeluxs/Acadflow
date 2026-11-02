<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorReward extends Model
{
    public const KINDS = ['referral_cash' => 'Referral cash (withdrawable)', 'wallet_credit' => 'Study wallet credit', 'data_allowance' => 'Data allowance', 'performance_bonus' => 'Performance bonus', 'recognition' => 'Certificate / recognition'];

    /** Kinds that must be backed by one qualified referral of the same ambassador. */
    public const REFERRAL_KINDS = ['performance_bonus', 'referral_cash'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['referral_id' => 'integer', 'amount_minor' => 'integer', 'fulfilled_at' => 'datetime']; }
    public function application() { return $this->belongsTo(AmbassadorApplication::class, 'ambassador_application_id'); }
}
