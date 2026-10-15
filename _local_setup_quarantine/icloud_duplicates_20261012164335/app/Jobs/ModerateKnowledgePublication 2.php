<?php
namespace App\Jobs;
use App\Models\KnowledgeModerationReport;
use App\Services\Knowledge\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ModerateKnowledgePublication implements ShouldQueue
{
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public int $tries=3; public int $backoff=60;
 public function __construct(public KnowledgeModerationReport $report){}
 public function handle(ModerationService $service):void{$service->process($this->report->fresh());}
 public function uniqueId():string{return 'knowledge-moderation-'.$this->report->id;}
}
