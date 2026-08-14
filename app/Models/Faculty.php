<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Faculty extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'university_id',
        'name',
        'short_name',
        'code',
        'dean_id',
        'is_active',
        'catalog_source',
        'is_catalog_template',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_catalog_template' => 'boolean'];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function dean(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
