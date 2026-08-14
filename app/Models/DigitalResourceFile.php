<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalResourceFile extends Model
{
    protected $fillable = [
        'knowledge_publication_id',
        'media_asset_id',
        'label',
        'is_preview',
        'download_limit',
    ];

    public function publication(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
    public function mediaAsset(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class); }

    protected function casts(): array
    {
        return [
            'is_preview' => 'boolean',
            'download_limit' => 'integer',
        ];
    }
}
