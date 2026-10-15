<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadinessSnapshot extends Model
{
    protected $fillable = ['user_id', 'course_id', 'score', 'signals', 'narrative', 'generated_at'];

    protected function casts(): array
    {
        return ['signals' => 'array', 'score' => 'integer', 'generated_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
