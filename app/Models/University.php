<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'short_name',
        'code',
        'institution_type',
        'ownership',
        'state',
        'regulator',
        'catalog_source',
        'catalog_verified_at',
        'email',
        'phone',
        'address',
        'logo',
        'website',
        'timezone',
        'is_active',
        'adoption_status',
        'claimed_at',
        'adopted_at',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $university): void {
            if (empty($university->uuid)) $university->uuid = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'catalog_verified_at' => 'datetime',
            'claimed_at' => 'datetime',
            'adopted_at' => 'datetime',
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


    public function affiliations(): HasMany
    {
        return $this->hasMany(UserInstitutionAffiliation::class);
    }

    public function emailDomains(): HasMany
    {
        return $this->hasMany(InstitutionEmailDomain::class);
    }

    public function isOfficiallyAdopted(): bool
    {
        return $this->adoption_status === 'active';
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
