<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionGrade extends Model
{
    protected $fillable = [
        'submission_id',
        'user_id',
        'score',
        'max_score',
        'rubric_id',
        'feedback',
        'is_final',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'is_final' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(SubmissionRubric::class, 'rubric_id');
    }
}
