<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicChallengeTeamMember extends Model
{
    protected $fillable = ['academic_challenge_entry_id','user_id','role','status'];
    public function entry() { return $this->belongsTo(AcademicChallengeEntry::class, 'academic_challenge_entry_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
