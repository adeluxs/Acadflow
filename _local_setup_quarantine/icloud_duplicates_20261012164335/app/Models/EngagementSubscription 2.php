<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscribable_type',
        'subscribable_id',
        'frequency',
        'is_muted',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'is_muted' => 'boolean',
            'preferences' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
