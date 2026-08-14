<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingListMember extends Model
{
    protected $fillable = [
        'reading_list_id',
        'user_id',
        'role',
    ];
    public function readingList(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ReadingList::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
