<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AmbassadorApplication extends Model
{
    /** Applicant-facing wording for each stored status. */
    public const STATUS_LABELS = [
        'pending' => 'Submitted · under review',
        'approved' => 'Approved',
        'rejected' => 'Not approved',
        'suspended' => 'Suspended',
        'withdrawn' => 'Withdrawn',
    ];

    /** Statuses from which the applicant may edit and resubmit. */
    public const RESUBMITTABLE = ['rejected', 'withdrawn'];

    // Status, review, referral code and institution link are set only by server code.
    protected $guarded = ['id', 'uuid', 'referral_code'];
    protected $hidden = ['browser_hash'];

    protected function casts(): array
    {
        return ['terms_accepted_at' => 'datetime', 'reviewed_at' => 'datetime', 'submitted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->uuid ??= (string) Str::uuid();
            $application->referral_code ??= Str::lower(Str::random(16));
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function user() { return $this->belongsTo(User::class); }
    public function referrals() { return $this->hasMany(AmbassadorReferral::class); }
    public function reports() { return $this->hasMany(AmbassadorReport::class); }
    public function rewards() { return $this->hasMany(AmbassadorReward::class); }
    public function university() { return $this->belongsTo(University::class); }
}
