<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionComment extends Model
{
    protected $fillable = [
        'submission_id',
        'user_id',
        'parent_id',
        'version_id',
        'content',
        'type',
        'status',
        'page_number',
        'x_position',
        'y_position',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'x_position' => 'float',
            'y_position' => 'float',
            'is_internal' => 'boolean',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SubmissionComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SubmissionComment::class, 'parent_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SubmissionVersion::class, 'version_id');
    }
}
