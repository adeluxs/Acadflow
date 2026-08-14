<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    protected $fillable = ['university_id', 'parent_id', 'name', 'slug', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function publications(): HasMany { return $this->hasMany(KnowledgePublication::class, 'category_id'); }
}
