<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicChallengeScore extends Model
{
    protected $fillable = [
        'academic_challenge_entry_id',
        'judge_id',
        'criterion',
        'score',
        'feedback',
        'is_ai_assisted',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_ai_assisted' => 'boolean',
            'metadata' => 'array',
        ];
    }
    public function entry(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AcademicChallengeEntry::class, 'academic_challenge_entry_id'); }
    public function judge(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'judge_id'); }

}
