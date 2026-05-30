<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'course_id',
        'semester_id',
        'name',
        'description',
        'leader_id',
        'status',
        'is_locked',
        'max_members',
        'formed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'formed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->uuid)) {
                $group->uuid = Str::uuid()->toString();
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
