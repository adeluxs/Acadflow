<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AcademicEventInvitation extends Model
{
    protected $fillable = [
        'uuid', 'academic_event_id', 'inviter_id', 'invitee_id', 'email',
        'status', 'token_hash', 'expires_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AcademicEvent::class, 'academic_event_id'); }
    public function inviter(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'inviter_id'); }
    public function invitee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'invitee_id'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
