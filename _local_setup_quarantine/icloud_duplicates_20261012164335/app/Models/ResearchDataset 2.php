<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchDataset extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'media_asset_id',
        'uploaded_by',
        'name',
        'description',
        'access_level',
        'schema_metadata',
        'ethics_metadata',
    ];

    protected function casts(): array
    {
        return [
            'schema_metadata' => 'array',
            'ethics_metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function mediaAsset(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class); }
    public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
