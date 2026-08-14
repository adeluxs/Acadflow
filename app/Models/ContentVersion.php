<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVersion extends Model
{
    public $timestamps = false;
    protected $fillable = ['content_document_id', 'author_id', 'version_number', 'body', 'change_summary', 'is_snapshot', 'metadata', 'created_at'];
    protected function casts(): array { return ['is_snapshot' => 'boolean', 'metadata' => 'array', 'created_at' => 'datetime']; }
    public function document(): BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
