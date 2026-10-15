<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningPathItem extends Model
{
    protected $fillable = [
        'learning_path_id',
        'item_type',
        'item_id',
        'title',
        'description',
        'position',
        'is_required',
        'estimated_minutes',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
            'estimated_minutes' => 'integer',
            'settings' => 'array',
        ];
    }
    public function path(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LearningPath::class, 'learning_path_id'); }
    public function progressRecords(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(LearningProgress::class); }
    public function item(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }

}
