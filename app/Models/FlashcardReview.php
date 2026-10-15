<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardReview extends Model
{
    protected $fillable = ['deck_id', 'user_id', 'card_index', 'rating', 'scheduled_days', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function deck(): BelongsTo { return $this->belongsTo(FlashcardDeck::class, 'deck_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
