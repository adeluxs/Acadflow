<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchMeetingReminder extends Model
{
    protected $fillable = [
        'research_meeting_id',
        'user_id',
        'remind_at',
        'channel',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
    public function meeting(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchMeeting::class, 'research_meeting_id'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
