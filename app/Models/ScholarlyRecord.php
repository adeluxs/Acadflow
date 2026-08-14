<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScholarlyRecord extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'provider',
        'external_identifier',
        'record_type',
        'title',
        'authors',
        'publication_year',
        'doi',
        'orcid',
        'url',
        'abstract',
        'concepts',
        'raw_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'publication_year' => 'integer',
            'concepts' => 'array',
            'raw_data' => 'array',
            'fetched_at' => 'datetime',
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
}
