<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class KnowledgePromotionCampaign extends Model
{
    protected $fillable = ['uuid', 'creator_id', 'knowledge_publication_id', 'knowledge_promotion_package_id', 'title', 'price', 'status', 'starts_at', 'ends_at', 'impressions', 'clicks', 'reviewed_at', 'reviewed_by', 'review_note', 'metadata'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'reviewed_at' => 'datetime', 'impressions' => 'integer', 'clicks' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $campaign) => $campaign->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(KnowledgePromotionPackage::class, 'knowledge_promotion_package_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function orderItems(): MorphMany
    {
        return $this->morphMany(CommerceOrderItem::class, 'purchasable');
    }

    public function isDeliverable(): bool
    {
        return $this->status === 'active' && $this->starts_at?->isPast() && $this->ends_at?->isFuture();
    }
}
