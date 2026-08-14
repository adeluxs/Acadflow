<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PayoutAccount extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'provider', 'account_name', 'account_number', 'bank_code',
        'bank_name', 'currency', 'is_default', 'is_verified', 'metadata',
    ];

    protected $appends = ['masked_account_number'];
    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') return $value;
                try { return Crypt::decryptString($value); } catch (\Throwable) { return $value; }
            },
            set: fn (?string $value): ?string => $value === null || $value === '' ? $value : Crypt::encryptString($value),
        );
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $number = preg_replace('/\s+/', '', (string) $this->account_number);
        if ($number === '') return 'Not set';
        return str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4);
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WithdrawalRequest::class); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
