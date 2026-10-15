<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningProgress extends Model
{
    protected $fillable = [
        'learning_enrollment_id',
        'learning_path_item_id',
        'status',
        'score',
        'time_spent_seconds',
        'completed_at',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'time_spent_seconds' => 'integer',
            'completed_at' => 'datetime',
            'state' => 'array',
        ];
    }
    public function enrollment(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id'); }
    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LearningPathItem::class, 'learning_path_item_id'); }

}
