<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingState extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'current_step',
        'data',
        'skipped_steps',
        'started_at',
        'last_saved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'data' => 'array',
            'skipped_steps' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
