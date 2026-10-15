<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContentComment extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid', 'content_document_id', 'user_id', 'parent_id', 'section_key', 'type', 'body', 'status', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['resolved_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function document(): BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
