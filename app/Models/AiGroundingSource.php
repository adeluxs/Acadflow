<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGroundingSource extends Model
{
    protected $fillable = [
        'ai_grounding_session_id',
        'search_document_id',
        'search_chunk_id',
        'source_type',
        'source_id',
        'title',
        'locator',
        'excerpt',
        'relevance_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'relevance_score' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
