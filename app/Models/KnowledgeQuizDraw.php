<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The questions (and option order) one reader was shown for one publication. */
class KnowledgeQuizDraw extends Model
{
    protected $fillable = ['user_id', 'knowledge_publication_id', 'questions'];

    protected function casts(): array
    {
        return ['questions' => 'array'];
    }
}
