<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeSeries extends Model
{
    use SoftDeletes;

    protected $table = 'knowledge_series';

    protected $fillable = ['uuid', 'creator_id', 'category_id', 'publication_type_id', 'title', 'slug', 'description', 'genre', 'visibility', 'status', 'completion_status', 'cover_path', 'metadata', 'published_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $series): void {
            $series->uuid ??= (string) Str::uuid();
            $base = Str::slug($series->slug ?: $series->title) ?: Str::lower(Str::random(10));
            $slug = $base;
            $counter = 2;
            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$counter++;
            }
            $series->slug = $slug;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function category()
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function publicationType()
    {
        return $this->belongsTo(KnowledgePublicationType::class, 'publication_type_id');
    }

    public function chapters()
    {
        return $this->hasMany(KnowledgePublication::class, 'series_id')->orderBy('chapter_number');
    }

    public function progress()
    {
        return $this->hasMany(KnowledgeReadingProgress::class, 'knowledge_series_id');
    }
}
