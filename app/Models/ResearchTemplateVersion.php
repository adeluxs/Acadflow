<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchTemplateVersion extends Model
{
    protected $fillable = [
        'uuid',
        'research_type_id',
        'version',
        'name',
        'template_schema',
        'validation_rules',
        'citation_style',
        'is_active',
        'effective_from',
        'retired_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'template_schema' => 'array',
            'validation_rules' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function researchType(): BelongsTo { return $this->belongsTo(ResearchType::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
