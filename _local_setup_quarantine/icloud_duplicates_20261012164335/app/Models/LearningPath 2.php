<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'creator_id',
        'title',
        'slug',
        'description',
        'visibility',
        'access_type',
        'price',
        'status',
        'certificate_enabled',
        'outcomes',
        'settings',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'certificate_enabled' => 'boolean',
            'outcomes' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(LearningPathItem::class)->orderBy('position'); }
    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(LearningEnrollment::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
