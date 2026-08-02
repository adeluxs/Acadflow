<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $modelType,
            'entity_id' => $modelId,
            'old_values' => json_encode($oldValue),
            'new_values' => json_encode($newValue),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
