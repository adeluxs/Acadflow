<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class KnowledgeModerationReport extends Model
{
    protected $fillable=['uuid','knowledge_publication_id','requested_by','plagiarism_check_id','status','quality_score','similarity_score','risk_level','findings','summary','human_review_required','completed_at'];
    protected function casts(): array { return ['quality_score'=>'decimal:2','similarity_score'=>'decimal:2','findings'=>'array','human_review_required'=>'boolean','completed_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid??=(string)Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function publication(){ return $this->belongsTo(KnowledgePublication::class,'knowledge_publication_id'); }
    public function plagiarismCheck(){ return $this->belongsTo(PlagiarismCheck::class); }
    public function requester(){ return $this->belongsTo(User::class, 'requested_by'); }
}
