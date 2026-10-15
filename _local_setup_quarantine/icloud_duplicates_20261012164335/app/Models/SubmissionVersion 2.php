<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'version_number',
        'file_name',
        'file_path',
        'disk',
        'media_asset_id',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
            'is_current' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function mediaAsset(): BelongsTo { return $this->belongsTo(MediaAsset::class); }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
