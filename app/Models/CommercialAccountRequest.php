<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommercialAccountRequest extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'commercial_account_id',
        'requested_by_user_id',
        'request_type',
        'requested_amount_minor',
        'currency',
        'requested_changes',
        'message',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_minor' => 'integer',
            'requested_changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->uuid ??= (string) Str::uuid());
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function commercialAccount(): BelongsTo
    {
        return $this->belongsTo(CommercialAccount::class);
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function label(): string
    {
        return match ($this->request_type) {
            'account_setup' => 'Commercial account setup',
            'add_funds' => 'Add prepaid funds',
            'change_terms' => 'Change account terms',
            'sponsorship' => 'Change sponsored access',
            default => 'Commercial account request',
        };
    }
}
