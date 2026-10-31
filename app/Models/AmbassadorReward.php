<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorReward extends Model
{
    public const KINDS = ['wallet_credit' => 'Study wallet credit', 'data_allowance' => 'Data allowance', 'performance_bonus' => 'Performance bonus', 'recognition' => 'Certificate / recognition'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['referral_id' => 'integer', 'amount_minor' => 'integer', 'fulfilled_at' => 'datetime']; }
    public function application() { return $this->belongsTo(AmbassadorApplication::class, 'ambassador_application_id'); }
}
