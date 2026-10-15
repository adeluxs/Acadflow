<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AcademicEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'faculty_id',
        'organizer_id',
        'knowledge_community_id',
        'group_id',
        'category_id',
        'cover_media_id',
        'title',
        'slug',
        'description',
        'event_type',
        'format',
        'timezone',
        'visibility',
        'status',
        'starts_at',
        'ends_at',
        'registration_deadline',
        'registration_mode',
        'location',
        'online_url',
        'capacity',
        'waitlist_enabled',
        'requires_moderation',
        'certificate_enabled',
        'settings',
        'published_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'capacity' => 'integer',
            'waitlist_enabled' => 'boolean',
            'requires_moderation' => 'boolean',
            'certificate_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(KnowledgeTag::class, 'academic_event_tag')->withTimestamps(); }
    public function organizer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function university(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(University::class); }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Department::class); }
    public function faculty(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Faculty::class); }
    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Group::class); }
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCategory::class); }
    public function coverMedia(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class, 'cover_media_id'); }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCommunity::class, 'knowledge_community_id'); }
    public function coOrganizers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(User::class, 'academic_event_organizers')->withPivot(['role','added_by'])->withTimestamps(); }
    public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicEventReminder::class); }
    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicEventInvitation::class); }
    public function registrations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicEventRegistration::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
