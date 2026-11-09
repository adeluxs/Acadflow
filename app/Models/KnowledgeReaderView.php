<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeReaderView extends Model
{
    protected $fillable = ['user_id', 'knowledge_publication_id', 'period', 'metered', 'first_opened_at', 'last_opened_at'];

    protected function casts(): array
    {
        return ['metered' => 'boolean', 'first_opened_at' => 'datetime', 'last_opened_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
}
