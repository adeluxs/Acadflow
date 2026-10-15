<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeFollow extends Model
{
    public $timestamps = false;
    protected $fillable = ['follower_id', 'target_type', 'target_id', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function follower(): BelongsTo { return $this->belongsTo(User::class, 'follower_id'); }
}
