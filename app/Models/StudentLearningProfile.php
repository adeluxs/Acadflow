<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personalization preferences a student sets to tailor AI explanations.
 */
class StudentLearningProfile extends Model
{
    protected $fillable = [
        'user_id',
        'pace',
        'level',
        'explanation_style',
        'language',
        'reading_level',
        'goals',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable preference lines for injection into an AI prompt.
     *
     * @return list<string>
     */
    public function summaryLines(): array
    {
        return array_values(array_filter([
            $this->level ? 'Preferred level: '.$this->level : null,
            $this->pace ? 'Preferred pace: '.$this->pace : null,
            $this->explanation_style ? 'Preferred explanation style: '.$this->explanation_style : null,
            $this->reading_level ? 'Preferred reading level: '.$this->reading_level : null,
            $this->language ? 'Preferred language: '.$this->language : null,
            $this->goals ? 'Learning goals: '.$this->goals : null,
        ]));
    }

    public function isEmpty(): bool
    {
        return $this->summaryLines() === [];
    }
}
