<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'score',
        'reason',
        'signals',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'signals' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
