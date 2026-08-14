<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCitationRecord extends Model
{
    protected $fillable = [
        'knowledge_publication_id',
        'provider',
        'external_work_id',
        'citing_work_id',
        'citing_title',
        'citing_url',
        'publication_year',
        'provenance',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'provenance' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
    public function publication(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }

}
