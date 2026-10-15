<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAccessLog extends Model
{
    /** The access-log table is append-only and intentionally has created_at only. */
    public $timestamps = false;
    protected $fillable = [
        'media_asset_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
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
