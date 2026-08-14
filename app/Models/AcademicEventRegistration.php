<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicEventRegistration extends Model
{
    protected $fillable = [
        'academic_event_id',
        'user_id',
        'checked_in_by',
        'status',
        'registered_at',
        'attended_at',
        'cancelled_at',
        'certificate_path',
        'check_in_code',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'attended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AcademicEvent::class, 'academic_event_id'); }
    public function checkedInBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'checked_in_by'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
