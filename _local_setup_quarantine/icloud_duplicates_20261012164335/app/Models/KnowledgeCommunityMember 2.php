<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeCommunityMember extends Model
{
    protected $fillable = [
        'knowledge_community_id',
        'user_id',
        'invited_by',
        'role',
        'status',
        'reviewed_by',
        'reviewed_at',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCommunity::class, 'knowledge_community_id'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
