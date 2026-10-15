<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgePublication extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid', 'university_id', 'department_id', 'creator_id', 'source_research_project_id', 'content_document_id', 'category_id', 'title', 'slug', 'doi', 'content_type', 'language', 'excerpt', 'status', 'visibility', 'access_type', 'price', 'moderation_note', 'moderated_by', 'moderation_report_id', 'submitted_at', 'scheduled_at', 'published_at', 'featured_at', 'pinned_at', 'view_count', 'bookmark_count', 'comment_count', 'share_count', 'download_count', 'reading_time_minutes', 'metadata'];
    protected function casts(): array { return ['price' => 'decimal:2', 'submitted_at' => 'datetime', 'scheduled_at' => 'datetime', 'published_at' => 'datetime', 'featured_at' => 'datetime', 'pinned_at' => 'datetime', 'metadata' => 'array', 'view_count' => 'integer', 'bookmark_count' => 'integer', 'comment_count' => 'integer', 'share_count' => 'integer', 'download_count' => 'integer', 'reading_time_minutes' => 'integer']; }
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
            $model->slug = static::uniqueSlug($model->slug ?: $model->title);
        });
    }
    protected static function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(10));
        $slug = $base;
        $counter = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) { $slug = $base.'-'.$counter++; }
        return $slug;
    }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function sourceResearchProject(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'source_research_project_id'); }
    public function document(): BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function category(): BelongsTo { return $this->belongsTo(KnowledgeCategory::class, 'category_id'); }
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
    public function moderationReport(): BelongsTo { return $this->belongsTo(KnowledgeModerationReport::class, 'moderation_report_id'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(KnowledgeTag::class, 'knowledge_publication_tag')->withTimestamps(); }
    public function bookmarks(): HasMany { return $this->hasMany(KnowledgeBookmark::class); }
    public function events(): HasMany { return $this->hasMany(KnowledgeEvent::class); }
    public function citations(): HasMany { return $this->citationsMade(); }
    public function citationsMade(): HasMany { return $this->hasMany(KnowledgeCitation::class, 'citing_publication_id'); }
    public function citationsReceived(): HasMany { return $this->hasMany(KnowledgeCitation::class, 'cited_publication_id'); }
    public function moderationReports(): HasMany { return $this->hasMany(KnowledgeModerationReport::class); }
    public function referenceLinks(): HasMany { return $this->hasMany(AcademicReferenceLink::class); }
    public function digitalFiles(): HasMany { return $this->hasMany(DigitalResourceFile::class); }
    public function externalCitations(): HasMany { return $this->hasMany(ExternalCitationRecord::class); }
    public function orderItems(): HasMany { return $this->hasMany(CommerceOrderItem::class, 'purchasable_id')->where('purchasable_type', self::class); }
    public function getRouteKeyName(): string { return 'slug'; }
    public function isPublished(): bool { return $this->status === 'published' && $this->published_at?->isPast(); }
}
