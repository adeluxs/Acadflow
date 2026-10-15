<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReputationProfile extends Model
{
    protected $fillable = [
        'user_id',
        'university_id',
        'knowledge_score',
        'quality_score',
        'research_impact_score',
        'community_score',
        'overall_score',
        'level_key',
        'publication_count',
        'citation_count',
        'follower_count',
        'breakdown',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'knowledge_score' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'research_impact_score' => 'decimal:2',
            'community_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'publication_count' => 'integer',
            'citation_count' => 'integer',
            'follower_count' => 'integer',
            'breakdown' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
}
