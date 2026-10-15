<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReputationEvent extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'event_type',
        'points',
        'source_type',
        'source_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
