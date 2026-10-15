<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeadlineCoachPlan extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'items', 'summary', 'provider', 'model', 'error_code', 'generated_at'];

    protected function casts(): array
    {
        return ['items' => 'array', 'generated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->uuid)) $plan->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
