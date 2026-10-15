<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscoveryEvent extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'university_id',
        'target_type',
        'target_id',
        'event_type',
        'weight',
        'privacy_scope',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
