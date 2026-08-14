<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementShare extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'shareable_type',
        'shareable_id',
        'channel',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
