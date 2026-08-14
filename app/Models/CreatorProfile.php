<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'headline',
        'biography',
        'expertise',
        'position',
        'orcid',
        'website',
        'social_links',
        'external_profiles',
        'orcid_synced_at',
        'verification_status',
        'privacy_settings',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'social_links' => 'array',
            'external_profiles' => 'array',
            'orcid_synced_at' => 'datetime',
            'privacy_settings' => 'array',
            'is_public' => 'boolean',
        ];
    }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }

}
