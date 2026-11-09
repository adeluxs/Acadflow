<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeQuizReport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['knowledge_quiz_question_id', 'user_id', 'reason'];

    public function question(): BelongsTo { return $this->belongsTo(KnowledgeQuizQuestion::class, 'knowledge_quiz_question_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
