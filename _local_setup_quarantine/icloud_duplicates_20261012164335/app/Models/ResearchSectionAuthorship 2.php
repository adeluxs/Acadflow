<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchSectionAuthorship extends Model
{
    /** This table is append-only and stores created_at without updated_at. */
    public $timestamps = false;
    protected $fillable = [
        'research_section_id',
        'content_version_id',
        'user_id',
        'words_added',
        'words_removed',
        'characters_added',
        'characters_removed',
        'contribution_score',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'words_added' => 'integer',
            'words_removed' => 'integer',
            'characters_added' => 'integer',
            'characters_removed' => 'integer',
            'contribution_score' => 'decimal:4',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
