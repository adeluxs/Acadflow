<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchSpecializedLink extends Model
{
    protected $fillable = [
        'research_project_id',
        'workspace_type',
        'source_type',
        'source_id',
        'settings',
    ];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
