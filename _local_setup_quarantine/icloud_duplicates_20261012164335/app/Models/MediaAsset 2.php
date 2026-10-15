<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'owner_id',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256',
        'visibility',
        'scan_status',
        'scan_provider',
        'scan_result',
        'preview_status',
        'preview_metadata',
        'metadata',
        'scanned_at',
        'quarantined_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_result' => 'array',
            'preview_metadata' => 'array',
            'metadata' => 'array',
            'scanned_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function attachable(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
    public function accessLogs(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(MediaAccessLog::class); }
    public function digitalResources(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(DigitalResourceFile::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
