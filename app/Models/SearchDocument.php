<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SearchDocument extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'searchable_type',
        'searchable_id',
        'content_type',
        'title',
        'summary',
        'body',
        'keywords',
        'visibility',
        'access_type',
        'embedding',
        'embedding_dimensions',
        'checksum',
        'metadata',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function chunks(): HasMany { return $this->hasMany(SearchChunk::class)->orderBy('position'); }
    public function searchable(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
}
