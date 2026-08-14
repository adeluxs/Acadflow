<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KnowledgePollVote extends Model { public $timestamps=false; protected $fillable=['knowledge_poll_option_id','user_id','created_at']; public function option(){return $this->belongsTo(KnowledgePollOption::class,'knowledge_poll_option_id');} public function user(){return $this->belongsTo(User::class);} }
