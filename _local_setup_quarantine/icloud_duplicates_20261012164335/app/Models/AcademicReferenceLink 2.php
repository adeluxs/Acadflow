<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicReferenceLink extends Model
{
    protected $fillable = ['academic_reference_id', 'content_document_id', 'research_project_id', 'knowledge_publication_id', 'purpose'];
    public function reference(): BelongsTo { return $this->belongsTo(AcademicReference::class, 'academic_reference_id'); }
    public function document(): BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function researchProject(): BelongsTo { return $this->belongsTo(ResearchProject::class); }
    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
}
