<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReadingList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'owner_id',
        'research_project_id',
        'course_id',
        'title',
        'description',
        'list_type',
        'visibility',
        'is_collaborative',
    ];

    protected function casts(): array
    {
        return [
            'is_collaborative' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ReadingListItem::class)->orderBy('position'); }
    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ReadingListMember::class); }
    public function researchProject(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchProject::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
