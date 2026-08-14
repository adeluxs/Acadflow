<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlagiarismMatch extends Model
{
    protected $fillable = [
        'plagiarism_check_id',
        'source_type',
        'source_identifier',
        'source_title',
        'source_url',
        'source_hash',
        'source_excerpt',
        'target_locations',
        'similarity_score',
        'citation_status',
        'provider',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_locations' => 'array',
            'similarity_score' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
