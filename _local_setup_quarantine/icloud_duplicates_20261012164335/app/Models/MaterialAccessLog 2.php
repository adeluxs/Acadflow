<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialAccessLog extends Model
{
    protected $fillable = [
        'material_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
