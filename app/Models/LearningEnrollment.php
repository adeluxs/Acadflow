<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningEnrollment extends Model
{
    protected $fillable = [
        'learning_path_id',
        'user_id',
        'status',
        'progress',
        'current_item_id',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
    public function path(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LearningPath::class, 'learning_path_id'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function currentItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LearningPathItem::class, 'current_item_id'); }
    public function progressRecords(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(LearningProgress::class); }

}
