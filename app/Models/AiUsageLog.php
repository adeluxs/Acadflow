<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'university_id',
        'department_id',
        'feature',
        'mode',
        'source',
        'cached',
        'success',
        'processing_time',
        'cost',
        'estimated_savings',
        'score',
        'issue_count',
    ];

    protected function casts(): array
    {
        return [
            'cached' => 'boolean',
            'success' => 'boolean',
            'processing_time' => 'decimal:4',
            'cost' => 'decimal:6',
            'estimated_savings' => 'decimal:6',
            'score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
