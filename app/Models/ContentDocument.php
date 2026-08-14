<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContentDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'university_id', 'owner_id', 'document_type', 'editor_mode', 'title', 'body', 'status', 'visibility', 'version_number', 'word_count', 'metadata', 'recovery_metadata', 'autosaved_at', 'last_synced_at', 'locked_at', 'locked_by'];
    protected function casts(): array { return ['metadata' => 'array', 'recovery_metadata' => 'array', 'autosaved_at' => 'datetime', 'last_synced_at' => 'datetime', 'locked_at' => 'datetime', 'version_number' => 'integer', 'word_count' => 'integer']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function locker(): BelongsTo { return $this->belongsTo(User::class, 'locked_by'); }
    public function versions(): HasMany { return $this->hasMany(ContentVersion::class)->orderByDesc('version_number'); }
    public function comments(): HasMany { return $this->hasMany(ContentComment::class)->orderBy('created_at'); }
    public function researchSections(): HasMany { return $this->hasMany(ResearchSection::class); }
    public function publications(): HasMany { return $this->hasMany(KnowledgePublication::class); }
    public function communityPosts(): HasMany { return $this->hasMany(KnowledgeCommunityPost::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
