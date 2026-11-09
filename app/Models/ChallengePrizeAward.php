<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChallengePrizeAward extends Model
{
    protected $fillable = ['uuid', 'challenge_prize_fund_id', 'academic_challenge_entry_id', 'user_id', 'rank', 'amount_minor', 'prize_type', 'paid_by', 'paid_at'];

    protected function casts(): array { return ['amount_minor' => 'integer', 'rank' => 'integer', 'paid_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::creating(fn (self $award) => $award->uuid ??= (string) Str::uuid());
    }

    public function fund(): BelongsTo { return $this->belongsTo(ChallengePrizeFund::class, 'challenge_prize_fund_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
