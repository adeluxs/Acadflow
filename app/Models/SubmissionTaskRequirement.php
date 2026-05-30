<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionTaskRequirement extends Model
{
    protected $fillable = [
        'submission_task_id',
        'requirement_type',
        'name',
        'description',
        'constraints',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'constraints' => 'array',
            'is_mandatory' => 'boolean',
        ];
    }

    public $timestamps = true;

    public function task(): BelongsTo
    {
        return $this->belongsTo(SubmissionTask::class, 'submission_task_id');
    }
}
