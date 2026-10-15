<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicChallengeVote extends Model { public $timestamps=false; protected $fillable=['academic_challenge_entry_id','user_id','created_at']; public function entry(){return $this->belongsTo(AcademicChallengeEntry::class,'academic_challenge_entry_id');} public function user(){return $this->belongsTo(User::class);} }
