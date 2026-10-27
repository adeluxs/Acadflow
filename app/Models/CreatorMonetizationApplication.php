<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreatorMonetizationApplication extends Model
{
    protected $fillable = ['uuid', 'user_id', 'status', 'ownership_declaration', 'terms_accepted', 'statement', 'payout_account_id', 'reviewed_by', 'review_note', 'submitted_at', 'reviewed_at', 'suspended_at'];

    protected function casts(): array
    {
        return ['ownership_declaration' => 'boolean', 'terms_accepted' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'suspended_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' && ! $this->suspended_at;
    }
}
