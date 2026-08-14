<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchChunk extends Model
{
    protected $fillable = [
        'search_document_id',
        'position',
        'heading',
        'content',
        'token_count',
        'embedding',
        'checksum',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'token_count' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function searchDocument(): BelongsTo { return $this->belongsTo(SearchDocument::class); }
}
