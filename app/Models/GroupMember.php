<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'status',
        'invited_by',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array { return ['joined_at'=>'datetime','left_at'=>'datetime']; }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCourseAttribute(): ?Course
    {
        return $this->group?->course;
    }
}
