<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementReaction extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'reactable_type',
        'reactable_id',
        'reaction',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
