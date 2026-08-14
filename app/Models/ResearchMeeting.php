<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchMeeting extends Model
{
    protected $fillable = ['uuid', 'research_project_id', 'scheduled_by', 'scheduled_at', 'duration_minutes', 'location', 'online_url', 'agenda', 'notes', 'attendance', 'action_items', 'status', 'calendar_uid', 'completed_at'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'duration_minutes' => 'integer', 'attendance' => 'array', 'action_items' => 'array']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function scheduler(): BelongsTo { return $this->belongsTo(User::class, 'scheduled_by'); }
    public function attendees(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ResearchMeetingAttendee::class); }
    public function actionItemRecords(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ResearchActionItem::class); }
    public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ResearchMeetingReminder::class); }
}
