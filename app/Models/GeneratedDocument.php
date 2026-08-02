<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'submission_id',
        'template_id',
        'title',
        'file_path',
        'file_size',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $document) {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
