<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionTag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function discussions()
    {
        return $this->belongsToMany(Discussion::class, 'discussion_tag_discussion');
    }
}
