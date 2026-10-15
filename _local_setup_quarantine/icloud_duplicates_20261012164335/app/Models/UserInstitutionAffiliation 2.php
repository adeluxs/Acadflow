<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInstitutionAffiliation extends Model
{
    protected $fillable = ['uuid','user_id','university_id','faculty_id','department_id','affiliation_type','status','verification_method','source','identifier_hash','identifier_last4','verified_by','verified_at','review_note','metadata'];
    protected function casts(): array { return ['verified_at'=>'datetime','metadata'=>'array']; }
    protected static function booted(): void { static::creating(fn(self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function faculty(): BelongsTo { return $this->belongsTo(Faculty::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function isVerified(): bool { return $this->status === 'verified'; }
}
