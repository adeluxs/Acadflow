<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgePromotionPackage extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'duration_days', 'price', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'price' => 'decimal:2', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(KnowledgePromotionCampaign::class);
    }
}
