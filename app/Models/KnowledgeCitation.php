<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeCitation extends Model
{
    protected $fillable = ['citing_publication_id', 'cited_publication_id', 'academic_reference_id', 'external_identifier', 'source'];
    public function citingPublication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'citing_publication_id'); }
    public function citedPublication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'cited_publication_id'); }
    public function reference(): BelongsTo { return $this->belongsTo(AcademicReference::class, 'academic_reference_id'); }
}
