<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeWritingScore extends Model
{
    protected $fillable = ['knowledge_writing_entry_id', 'judge_id', 'scores', 'total', 'comment'];

    protected function casts(): array { return ['scores' => 'array', 'total' => 'float']; }

    public function entry(): BelongsTo { return $this->belongsTo(KnowledgeWritingEntry::class, 'knowledge_writing_entry_id'); }
    public function judge(): BelongsTo { return $this->belongsTo(User::class, 'judge_id'); }
}
