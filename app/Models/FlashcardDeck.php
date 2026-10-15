<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FlashcardDeck extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'material_id', 'title', 'cards',
        'provider', 'model', 'error_code',
    ];

    protected function casts(): array
    {
        return ['cards' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deck): void {
            if (empty($deck->uuid)) $deck->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function material(): BelongsTo { return $this->belongsTo(CourseMaterial::class, 'material_id'); }

    public function reviews(): HasMany { return $this->hasMany(FlashcardReview::class, 'deck_id'); }

    public function cardCount(): int
    {
        return is_array($this->cards) ? count($this->cards) : 0;
    }

    public function getRouteKeyName(): string { return 'uuid'; }
}
