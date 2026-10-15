<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AcademicReference extends Model
{
    protected $fillable = ['uuid', 'university_id', 'owner_id', 'title', 'abstract', 'authors', 'publication_year', 'source_type', 'journal', 'publisher', 'doi', 'isbn', 'url', 'pdf_media_asset_id', 'citation_key', 'external_ids', 'metadata'];
    protected function casts(): array { return ['authors' => 'array', 'external_ids' => 'array', 'metadata' => 'array', 'publication_year' => 'integer']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function links(): HasMany { return $this->hasMany(AcademicReferenceLink::class); }
    public function pdfAsset(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'pdf_media_asset_id'); }
    public function literatureNotes(): HasMany { return $this->hasMany(ResearchLiteratureNote::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
