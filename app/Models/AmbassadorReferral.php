<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorReferral extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    public function user() { return $this->belongsTo(User::class); }
    public function application() { return $this->belongsTo(AmbassadorApplication::class, 'ambassador_application_id'); }
    protected $hidden = ['browser_hash', 'email_hash', 'ip_hash', 'review_signature'];
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'registered_at' => 'datetime', 'retention_starts_at' => 'datetime', 'retention_ends_at' => 'datetime', 'activated_at' => 'datetime', 'retained_at' => 'datetime'];
    }
}
