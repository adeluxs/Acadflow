<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementMention extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'mentioned_user_id',
        'mentioned_by',
        'source_type',
        'source_id',
        'context',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
