<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'faculty_id',
        'name',
        'short_name',
        'code',
        'head_id',
        'is_active',
        'catalog_source',
        'is_catalog_template',
        'adoption_status',
        'adopted_at',
        'adoption_settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) $model->uuid = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_catalog_template' => 'boolean', 'adopted_at' => 'datetime', 'adoption_settings' => 'array'];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }


    public function affiliations(): HasMany
    {
        return $this->hasMany(UserInstitutionAffiliation::class);
    }

    public function isIndependentlyActive(): bool
    {
        return $this->adoption_status === 'independent_active';
    }

    public function institutionAffiliations() { return $this->hasMany(DepartmentInstitutionAffiliation::class); }

    public function isInstitutionManaged(): bool
    {
        return $this->adoption_status === 'institution_managed' || ($this->adoption_status === 'institution_affiliated' && (bool)data_get($this->adoption_settings, 'institution_data_access', false));
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
