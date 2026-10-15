<?php

namespace App\Models;

use App\Services\FeatureAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureFlag extends Model
{
    protected $fillable = [
        'name',
        'is_enabled',
        'description',
        'settings',
        'enabled_at',
        'enabled_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
            'enabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FeatureAccessService::forget());
        static::deleted(fn () => FeatureAccessService::forget());
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }

    /**
     * Backwards-compatible API: runtime availability is now resolved centrally.
     */
    public static function isEnabled(string $name, ?int $universityId = null): bool
    {
        return FeatureAccessService::effectiveStatus($name, $universityId) === FeatureAccessService::STATUS_ENABLED;
    }

    public static function settingsFor(string $name, ?int $universityId = null): array
    {
        return FeatureAccessService::settings($name, $universityId);
    }

    /**
     * Backwards-compatible toggle helper. New code should use
     * FeatureAccessService::setStatus().
     */
    public static function setEnabled(string $name, bool $enabled, ?int $userId = null, ?int $universityId = null, array $settings = []): void
    {
        $flag = FeatureAccessService::setStatus(
            $name,
            $enabled ? FeatureAccessService::STATUS_ENABLED : FeatureAccessService::STATUS_DISABLED,
            $userId,
            $universityId,
        );

        if ($settings === []) {
            return;
        }

        if ($universityId !== null) {
            $override = $flag->overrides()->where('university_id', $universityId)->first();
            if ($override) {
                $override->update(['settings' => array_replace_recursive((array) $override->settings, $settings)]);
            }
        } else {
            $flag->update(['settings' => array_replace_recursive((array) $flag->settings, $settings)]);
        }

        FeatureAccessService::forget();
    }
}
