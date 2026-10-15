<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionRubric extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'description',
        'criteria',
        'total_points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'total_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
