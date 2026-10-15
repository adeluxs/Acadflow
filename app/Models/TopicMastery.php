<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicMastery extends Model
{
    protected $table = 'topic_mastery';

    protected $fillable = ['user_id', 'course_id', 'topic', 'mastery', 'signals'];

    protected function casts(): array
    {
        return ['signals' => 'array', 'mastery' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
