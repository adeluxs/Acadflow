<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'field_name',
        'old_value',
        'new_value',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        string $action,
        ?int $userId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $fieldName = null,
    ): self {
        $attributes = [
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'field_name' => $fieldName,
            'old_value' => self::serializeValue($oldValue),
            'new_value' => self::serializeValue($newValue),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];

        // The original audit table used required entity_* fields. Preserve and
        // dual-write them while old installations transition to the normalized schema.
        if (Schema::hasColumn('audit_logs', 'entity_type')) {
            $attributes['entity_type'] = $modelType ?? 'system';
        }
        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $attributes['entity_id'] = $modelId ?? 0;
        }
        if (Schema::hasColumn('audit_logs', 'old_values')) {
            $attributes['old_values'] = self::legacyJsonValue($oldValue);
        }
        if (Schema::hasColumn('audit_logs', 'new_values')) {
            $attributes['new_values'] = self::legacyJsonValue($newValue);
        }

        return static::create($attributes);
    }

    protected static function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function legacyJsonValue(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : ['value' => $value];
    }
}
