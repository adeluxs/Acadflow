<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DepartmentInstitutionAffiliation extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['policy' => 'array', 'department_approved_at' => 'datetime', 'verified_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
