<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MaterialAnnotation extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'material_id', 'quote', 'note'];

    protected static function booted(): void
    {
        static::creating(function (self $annotation): void {
            if (empty($annotation->uuid)) $annotation->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function material(): BelongsTo { return $this->belongsTo(CourseMaterial::class, 'material_id'); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
