<?php

namespace App\Services\Knowledge;

use App\Ai\AiManager;
use App\Jobs\ModerateKnowledgePublication;
use App\Jobs\IndexSearchableContent;
use App\Jobs\RecalculateReputation;
use App\Models\KnowledgeModerationReport;
use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\AcademicIntegrity\PlagiarismService;
use App\Services\Discovery\SearchIndexService;
use Illuminate\Support\Facades\DB;

class ModerationService
{
    public function __construct(private readonly AiManager $ai, private readonly PlagiarismService $plagiarism) {}

    public function queue(KnowledgePublication $publication, User $actor): KnowledgeModerationReport
    {
        $report = $publication->moderationReports()->create(['requested_by' => $actor->id, 'status' => 'queued', 'summary' => 'Academic quality and similarity checks are queued.', 'human_review_required' => true]);
        $publication->update(['moderation_report_id' => $report->id]);
        ModerateKnowledgePublication::dispatch($report)
            ->onConnection(config('ai.queue_connection') ?: config('queue.default'))
            ->onQueue('ai');
        return $report;
    }

    public function process(KnowledgeModerationReport $report): KnowledgeModerationReport
    {
        $report->loadMissing('publication.document','publication.tags','publication.referenceLinks.reference','publication.creator');
        $publication = $report->publication;
        $actor = User::find($report->requested_by) ?: $publication->creator;
        $report->update(['status'=>'processing']);
        try {
            $text = trim(strip_tags((string) $publication->document?->body));
            $payload = [
                'title'=>$publication->title, 'text'=>$text, 'references'=>$publication->referenceLinks->map(fn($l)=>$l->reference?->toArray())->filter()->all(),
                'access_type'=>$publication->access_type, 'price'=>$publication->price, 'content_type'=>$publication->content_type,
                'visibility'=>$publication->visibility, 'tags'=>$publication->tags->pluck('name')->all(),
            ];
            $quality = $this->ai->analyze('knowledge_publication_validator',$payload,$actor,'knowledge:moderation:'.$publication->uuid);
            $similarity = $this->plagiarism->check($publication,$text,$actor,['threshold'=>20]);
            $risk = $similarity->risk_level === 'high' || $quality->severity === 'critical' ? 'high' : ($similarity->risk_level === 'medium' || $quality->severity === 'warning' ? 'medium' : 'low');
            $report->update([
                'plagiarism_check_id'=>$similarity->id,'status'=>'completed','quality_score'=>$quality->score,'similarity_score'=>$similarity->similarity_score,
                'risk_level'=>$risk,'summary'=>$quality->summary,'findings'=>[
                    'request_id'=>$quality->requestId,'quality'=>$quality->findings,'evidence'=>$quality->evidence,'suggested_actions'=>$quality->suggestedActions,
                    'confidence'=>$quality->confidence,'similarity_matches'=>$similarity->matches->map(fn($m)=>$m->only(['source_title','source_url','source_excerpt','target_locations','similarity_score','citation_status','provider']))->all(),
                    'ai_is_advisory'=>true,'similarity_is_not_a_misconduct_decision'=>true,
                ],'human_review_required'=>true,'completed_at'=>now(),
            ]);
        } catch (\Throwable $e) {
            report($e); $report->update(['status'=>'failed','summary'=>'Automated checks failed safely; a moderator must review manually.','findings'=>['error'=>class_basename($e),'human_review_required'=>true],'completed_at'=>now()]);
        }
        return $report->fresh(['plagiarismCheck.matches']);
    }

    public function publish(KnowledgePublication $publication, User $moderator, ?string $note = null): KnowledgePublication
    {
        return DB::transaction(function () use ($publication,$moderator,$note) {
            $publication->loadMissing('document','creator');
            $publication->update(['status'=>'published','moderation_note'=>$note,'moderated_by'=>$moderator->id,'published_at'=>$publication->published_at ?? now(),'scheduled_at'=>null]);
            $publication->document->update(['status'=>'published','visibility'=>$publication->visibility]);
            IndexSearchableContent::dispatch($publication::class, $publication->id)->onQueue('indexing');
            RecalculateReputation::dispatch($publication->creator_id)->onQueue('analytics');
            return $publication->fresh();
        });
    }
}
