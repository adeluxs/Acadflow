<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'uuid',
        'course_id',
        'semester_id',
        'lecturer_id',
        'qr_code',
        'qr_expires_at',
        'started_at',
        'ended_at',
        'status',
        'geofence_lat',
        'geofence_lng',
        'geofence_radius',
        'check_in_window',
        'late_threshold',
    ];

    protected function casts(): array
    {
        return [
            'qr_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'geofence_lat' => 'decimal:8',
            'geofence_lng' => 'decimal:8',
            'geofence_radius' => 'integer',
            'check_in_window' => 'integer',
            'late_threshold' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
