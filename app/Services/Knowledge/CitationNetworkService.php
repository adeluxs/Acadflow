<?php

namespace App\Services\Knowledge;

use App\Models\AcademicReferenceLink;
use App\Models\ExternalCitationRecord;
use App\Models\KnowledgeCitation;
use App\Models\KnowledgePublication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CitationNetworkService
{
    public function rebuild(KnowledgePublication $publication): int
    {
        $publication->loadMissing('document','referenceLinks.reference');
        return DB::transaction(function () use ($publication) {
            $publication->citationsMade()->delete();
            $body = (string) $publication->document?->body;
            $uuids = [];
            preg_match_all('/data-publication-uuid=["\']([a-f0-9-]{36})["\']/i',$body,$matches);
            $uuids = array_merge($uuids,$matches[1] ?? []);
            preg_match_all('#/knowledge-hub/([a-z0-9-]+)#i',$body,$slugMatches);
            $slugs = array_values(array_unique($slugMatches[1] ?? []));
            $internal = collect();
            if ($uuids !== [] || $slugs !== []) {
                $internal = KnowledgePublication::query()->where('status','published')->where(function($q) use($uuids,$slugs){ if($uuids!==[])$q->whereIn('uuid',array_values(array_unique($uuids))); if($slugs!==[])$q->orWhereIn('slug',$slugs); })->get();
            }
            foreach ($internal as $cited) {
                if ($cited->id === $publication->id) continue;
                KnowledgeCitation::firstOrCreate(['citing_publication_id'=>$publication->id,'cited_publication_id'=>$cited->id],['source'=>'internal']);
            }
            foreach ($publication->referenceLinks as $link) {
                $reference = $link->reference;
                KnowledgeCitation::firstOrCreate(['citing_publication_id'=>$publication->id,'academic_reference_id'=>$reference->id],['external_identifier'=>$reference->doi ?: $reference->isbn,'source'=>'reference_manager']);
            }
            return $publication->citationsMade()->count();
        });
    }

    public function graph(KnowledgePublication $publication, int $depth = 2): array
    {
        $seen=[];$nodes=[];$edges=[];$queue=[[$publication,0]];
        while($queue!==[]){[$current,$level]=array_shift($queue);if(isset($seen[$current->id])||$level>$depth)continue;$seen[$current->id]=true;$nodes[]=['id'=>$current->uuid,'title'=>$current->title,'creator'=>$current->creator?->full_name,'citations'=>$current->citationsReceived()->count()];foreach($current->citationsMade()->with('citedPublication.creator')->whereNotNull('cited_publication_id')->get() as $citation){$target=$citation->citedPublication;if(!$target)continue;$edges[]=['source'=>$current->uuid,'target'=>$target->uuid,'type'=>'internal'];$queue[]=[$target,$level+1];}}
        return ['nodes'=>$nodes,'edges'=>$edges];
    }

    public function syncExternal(KnowledgePublication $publication): int
    {
        if (!$publication->doi) return 0;
        $base=config('scholarly.providers.openalex.base_url','https://api.openalex.org');
        try {
            $work=Http::timeout(20)->retry(2,300)->get(rtrim($base,'/').'/works/https://doi.org/'.rawurlencode($publication->doi))->throw()->json();
            $openAlexId=$work['id']??null;if(!$openAlexId)return 0;
            $results=Http::timeout(20)->retry(2,300)->get(rtrim($base,'/').'/works',['filter'=>'cites:'.$openAlexId,'per-page'=>200])->throw()->json('results',[]);
            foreach($results as $item){ExternalCitationRecord::updateOrCreate(['knowledge_publication_id'=>$publication->id,'provider'=>'openalex','external_work_id'=>$openAlexId,'citing_work_id'=>$item['id']??null],['citing_title'=>$item['title']??null,'citing_url'=>$item['primary_location']['landing_page_url']??$item['id']??null,'publication_year'=>$item['publication_year']??null,'provenance'=>['retrieved_from'=>'OpenAlex','raw_identifier'=>$item['id']??null],'fetched_at'=>now()]);}
        } catch(\Throwable $e){report($e);}
        return $publication->externalCitations()->count();
    }

    public function rankings(?int $universityId=null): array
    {
        return KnowledgePublication::query()->where('status','published')->when($universityId,fn($q)=>$q->where('university_id',$universityId))->with(['creator'])->withCount('citationsReceived')->withCount('externalCitations')->orderByDesc('citations_received_count')->limit(100)->get()->map(fn($p)=>['publication_uuid'=>$p->uuid,'title'=>$p->title,'creator'=>$p->creator?->full_name,'internal_citations'=>$p->citations_received_count,'external_citations'=>$p->external_citations_count])->all();
    }
}
