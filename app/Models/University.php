<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'short_name',
        'code',
        'email',
        'phone',
        'address',
        'logo',
        'website',
        'timezone',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function currentSession(): HasMany
    {
        return $this->hasMany(AcademicSession::class)->where('is_current', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
