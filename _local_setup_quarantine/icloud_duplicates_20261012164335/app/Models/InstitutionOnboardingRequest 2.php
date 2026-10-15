<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InstitutionOnboardingRequest extends Model
{
    protected $fillable = [
        'uuid','requester_user_id','university_id','institution_name','short_name','code',
        'institution_type','ownership','state','regulator','email','phone','website','address',
        'representative_title','request_type','status','reviewed_by_user_id','reviewed_at','review_note','metadata',
    ];

    protected function casts(): array
    {
        return ['reviewed_at'=>'datetime','metadata'=>'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->uuid ??= (string) Str::uuid());
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }
}
