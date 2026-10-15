<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchArchive extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'version',
        'generated_by',
        'status',
        'disk',
        'package_path',
        'checksum',
        'manifest',
        'sealed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'manifest' => 'array',
            'sealed_at' => 'datetime',
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
