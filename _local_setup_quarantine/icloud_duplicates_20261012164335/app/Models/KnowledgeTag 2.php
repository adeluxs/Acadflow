<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeTag extends Model
{
    protected $fillable = ['name', 'slug'];
    public function publications(): BelongsToMany { return $this->belongsToMany(KnowledgePublication::class, 'knowledge_publication_tag')->withTimestamps(); }
}
