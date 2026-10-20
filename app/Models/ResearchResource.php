<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchResource extends Model
{
    protected $fillable = ['research_project_id', 'created_by', 'content_document_id', 'media_asset_id', 'kind', 'title', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function project() { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function document() { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function mediaAsset() { return $this->belongsTo(MediaAsset::class); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
