<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AcademicMembershipInvitation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
