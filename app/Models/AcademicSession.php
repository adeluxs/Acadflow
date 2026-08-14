<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AcademicSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'university_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class, 'academic_session_id');
    }

    public function currentSemester(): HasMany
    {
        return $this->hasMany(Semester::class, 'academic_session_id')->where('is_active', true);
    }
}
