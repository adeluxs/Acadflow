<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Prize money a community challenge creator has paid into AcadSyntra escrow. */
class ChallengePrizeFund extends Model
{
    public const TYPES = ['cash' => 'Cash (withdrawable)', 'study_credit' => 'Study credit'];

    protected $fillable = ['uuid', 'academic_challenge_id', 'funder_id', 'prize_type', 'prizes', 'total_minor', 'fee_percent', 'fee_minor',
        'from_earnings_minor', 'from_spending_minor', 'disbursed_minor', 'refunded_minor', 'status', 'funded_at', 'settled_at'];

    protected function casts(): array
    {
        return ['prizes' => 'array', 'total_minor' => 'integer', 'fee_percent' => 'integer', 'fee_minor' => 'integer', 'from_earnings_minor' => 'integer',
            'from_spending_minor' => 'integer', 'disbursed_minor' => 'integer', 'refunded_minor' => 'integer', 'funded_at' => 'datetime', 'settled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $fund) => $fund->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function challenge(): BelongsTo { return $this->belongsTo(AcademicChallenge::class, 'academic_challenge_id')->withoutGlobalScopes(); }
    public function funder(): BelongsTo { return $this->belongsTo(User::class, 'funder_id'); }
    public function awards(): HasMany { return $this->hasMany(ChallengePrizeAward::class)->orderBy('rank'); }

    public function isFunded(): bool { return in_array($this->status, ['funded', 'settled'], true); }
    public function heldMinor(): int { return max(0, $this->total_minor - $this->disbursed_minor - $this->refunded_minor); }
}
