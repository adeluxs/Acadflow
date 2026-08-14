<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KnowledgePollOption extends Model { protected $fillable=['knowledge_community_post_id','label','position']; public function post(){return $this->belongsTo(KnowledgeCommunityPost::class,'knowledge_community_post_id');} public function votes(){return $this->hasMany(KnowledgePollVote::class);} public function getRouteKeyName():string{return 'id';} }
