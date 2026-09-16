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
        $depth = max(0, min(5, $depth));
        $seen = [];
        $nodes = [];
        $edges = [];
        $frontier = [$publication->id];
        $level = 0;

        // Traverse breadth-first in batches. The previous implementation issued a
        // citation count query and an outgoing-citations query for every node.
        while ($frontier !== [] && $level <= $depth) {
            $ids = array_values(array_unique(array_filter($frontier, fn ($id) => ! isset($seen[$id]))));
            if ($ids === []) break;

            $publications = KnowledgePublication::query()
                ->select(['id', 'uuid', 'title', 'creator_id'])
                ->whereIn('id', $ids)
                ->with('creator:id,first_name,last_name')
                ->withCount('citationsReceived')
                ->get()
                ->keyBy('id');

            foreach ($publications as $current) {
                $seen[$current->id] = true;
                $nodes[] = [
                    'id' => $current->uuid,
                    'title' => $current->title,
                    'creator' => $current->creator?->full_name,
                    'citations' => (int) $current->citations_received_count,
                ];
            }

            if ($level >= $depth || $publications->isEmpty()) break;

            $citations = KnowledgeCitation::query()
                ->select(['id', 'citing_publication_id', 'cited_publication_id'])
                ->whereIn('citing_publication_id', $publications->keys())
                ->whereNotNull('cited_publication_id')
                ->with('citedPublication:id,uuid,title,creator_id')
                ->get();

            $next = [];
            foreach ($citations as $citation) {
                $source = $publications->get($citation->citing_publication_id);
                $target = $citation->citedPublication;
                if (! $source || ! $target) continue;

                $edges[] = ['source' => $source->uuid, 'target' => $target->uuid, 'type' => 'internal'];
                if (! isset($seen[$target->id])) $next[] = $target->id;
            }

            $frontier = $next;
            $level++;
        }

        return ['nodes' => $nodes, 'edges' => $edges];
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
