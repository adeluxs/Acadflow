<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeQuizQuestion extends Model
{
    public const SOURCE_CREATOR = 'creator';
    public const SOURCE_AI = 'ai';

    protected $fillable = ['knowledge_publication_id', 'question', 'options', 'correct_option', 'explanation', 'source', 'sort_order', 'is_active', 'report_count', 'retired_at', 'created_by'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_option' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean', 'report_count' => 'integer', 'retired_at' => 'datetime'];
    }

    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(KnowledgeQuizReport::class); }

    public function isAi(): bool { return $this->source === self::SOURCE_AI; }
}
