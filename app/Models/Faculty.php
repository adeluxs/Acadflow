<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'university_id',
        'name',
        'short_name',
        'code',
        'dean_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function dean(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
