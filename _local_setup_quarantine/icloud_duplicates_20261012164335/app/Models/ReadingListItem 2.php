<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingListItem extends Model
{
    protected $fillable = [
        'reading_list_id',
        'added_by',
        'item_type',
        'item_id',
        'position',
        'status',
        'note',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
    public function readingList(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ReadingList::class); }
    public function addedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'added_by'); }
    public function item(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }

}
