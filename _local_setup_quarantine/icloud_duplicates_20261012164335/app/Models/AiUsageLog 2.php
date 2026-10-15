<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'request_id', 'user_id', 'university_id', 'department_id', 'feature', 'mode', 'source',
        'provider', 'model', 'fallback_used', 'fallback_provider', 'error_type', 'grounding_used', 'metadata',
        'cached', 'success', 'processing_time', 'cost', 'estimated_savings', 'score', 'issue_count',
    ];

    protected function casts(): array
    {
        return [
            'fallback_used' => 'boolean',
            'grounding_used' => 'boolean',
            'metadata' => 'array',
            'cached' => 'boolean',
            'success' => 'boolean',
            'processing_time' => 'decimal:4',
            'cost' => 'decimal:6',
            'estimated_savings' => 'decimal:6',
            'score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
