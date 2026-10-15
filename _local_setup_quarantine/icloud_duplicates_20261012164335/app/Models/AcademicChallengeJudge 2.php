<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicChallengeJudge extends Model
{
    protected $fillable = ['academic_challenge_id','user_id','invited_by','status','accepted_at'];
    protected function casts(): array { return ['accepted_at'=>'datetime']; }
    public function challenge() { return $this->belongsTo(AcademicChallenge::class, 'academic_challenge_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function inviter() { return $this->belongsTo(User::class, 'invited_by'); }
}
