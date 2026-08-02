<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseMaterial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'course_id',
        'semester_id',
        'uploaded_by',
        'title',
        'description',
        'type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'topic',
        'week_number',
        'sequence_order',
        'is_public',
        'requires_enrollment',
        'is_visible',
        'published_at',
        'download_count',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $material) {
            if (empty($material->uuid)) {
                $material->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'requires_enrollment' => 'boolean',
            'is_visible' => 'boolean',
            'published_at' => 'datetime',
            'download_count' => 'integer',
            'week_number' => 'integer',
            'sequence_order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(MaterialAccessLog::class, 'material_id');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class, 'material_id');
    }

    /**
     * Get route key name
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Check if user can view this material
     */
    public function canBeViewedBy(User $user): bool
    {
        if (! $this->is_visible) {
            return false;
        }

        if ($this->is_public) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->requires_enrollment) {
            return $user->enrollments()
                ->where('course_id', $this->course_id)
                ->where('status', 'enrolled')
                ->exists();
        }

        return true;
    }
}
