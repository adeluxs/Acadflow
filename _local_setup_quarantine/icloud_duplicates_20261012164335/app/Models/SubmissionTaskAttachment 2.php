<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionTaskAttachment extends Model
{
    protected $fillable = [
        'submission_task_id',
        'file_name',
        'file_path',
        'disk',
        'media_asset_id',
        'mime_type',
        'file_size',
        'description',
        'type',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(SubmissionTask::class, 'submission_task_id');
    }
}
