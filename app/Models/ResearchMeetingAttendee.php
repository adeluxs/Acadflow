<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchMeetingAttendee extends Model
{
    protected $fillable = [
        'research_meeting_id',
        'user_id',
        'response',
        'attended',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
        ];
    }
    public function meeting(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchMeeting::class, 'research_meeting_id'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
