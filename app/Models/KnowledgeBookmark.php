<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBookmark extends Model
{
    public $timestamps = false;
    protected $fillable = ['knowledge_publication_id', 'user_id', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
