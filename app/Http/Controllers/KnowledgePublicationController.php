<?php

namespace App\Http\Controllers;

use App\Jobs\IndexSearchableContent;
use App\Models\AuditLog;
use App\Models\ContentVersion;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeTag;
use App\Services\ContentWorkspaceService;
use App\Services\Knowledge\CitationNetworkService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\PublicationService;
use App\Services\RichTextSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgePublicationController extends Controller
{
    public function manage(Request $request): View
    {
        $user = $request->user();
        $publications = KnowledgePublication::query()->with(['category','sourceResearchProject','moderationReport'])
            ->when(! $user->isAdmin(), fn($q)=>$q->where('creator_id',$user->id))
            ->when($user->isUniversityAdmin(), fn($q)=>$q->where('university_id',$user->university_id))
            ->when($user->isDepartmentAdmin(), fn($q)=>$q->where('department_id',$user->department_id))
            ->when($request->filled('status'), fn($q)=>$q->where('status',$request->input('status')))
            ->latest('updated_at')->paginate(20)->withQueryString();
        return view('knowledge.manage',compact('publications'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create',KnowledgePublication::class);
        return view('knowledge.form',['publication'=>new KnowledgePublication(),'categories'=>$this->categoriesFor($request)]);
    }

    public function store(Request $request, PublicationService $publications): RedirectResponse
    {
        $this->authorize('create', KnowledgePublication::class);
        $publication = $publications->createDraft($this->validated($request), $request->user());

        return redirect()->route('knowledge.manage.edit', $publication)->with('success', 'Knowledge Hub draft created.');
    }

    public function showManage(Request $request, KnowledgePublication $publication): View
    {
        $this->authorize('view', $publication);
        $publication->load([
            'document.versions.author', 'tags', 'category', 'creator', 'sourceResearchProject',
            'moderationReports.plagiarismCheck.matches', 'moderationReport', 'digitalFiles.mediaAsset',
        ]);

        return view('knowledge.manage-show', compact('publication'));
    }

    public function edit(Request $request, KnowledgePublication $publication): View|RedirectResponse
    {
        if (! $request->user()->can('update', $publication)) {
            // A creator must always be able to open their own publication. When
            // its workflow state is read-only (for example pending review), show
            // the management view instead of turning the Open action into a 403.
            if ($publication->creator_id === $request->user()->id || $request->user()->can('view', $publication)) {
                return redirect()->route('knowledge.manage.show', $publication)
                    ->with('info', 'This publication is currently read-only. You can still review its status and available workflow actions here.');
            }
            abort(403);
        }

        $publication->load(['document.versions.author','tags','sourceResearchProject','moderationReports.plagiarismCheck.matches','digitalFiles.mediaAsset']);
        return view('knowledge.form',compact('publication')+['categories'=>$this->categoriesFor($request)]);
    }

    public function update(Request $request, KnowledgePublication $publication, PublicationService $publications): RedirectResponse
    {
        $this->authorize('update', $publication);
        $publications->updateDraft($publication, $this->validated($request), $request->user());

        return back()->with('success', 'Publication saved with version history.');
    }

    public function preview(Request $request, KnowledgePublication $publication): View
    {
        abort_unless($publication->creator_id===$request->user()->id||$request->user()->can('moderate',$publication),403);$publication->load(['document','creator','category','tags','sourceResearchProject','digitalFiles.mediaAsset']);
        return view('knowledge.preview',['publication'=>$publication,'hasAccess'=>true]);
    }

    public function submit(Request $request, KnowledgePublication $publication, ModerationService $moderation): RedirectResponse
    {
        $this->authorize('submit',$publication);abort_if(trim(strip_tags((string)$publication->document->body))==='',422,'Publication content cannot be empty.');abort_if($publication->access_type==='premium'&&(float)$publication->price<=0,422,'Premium publications require a price.');
        $publication->update(['status'=>'pending_review','submitted_at'=>now(),'moderation_note'=>null]);$publication->document->update(['status'=>'review']);$report=$moderation->queue($publication,$request->user());
        return back()->with('success','Publication submitted. Automated quality and similarity checks are queued for the human moderator. Report '.$report->uuid.'.');
    }

    public function moderate(Request $request, KnowledgePublication $publication, ModerationService $moderation, CitationNetworkService $citations): RedirectResponse
    {
        $this->authorize('moderate',$publication);$data=$request->validate(['decision'=>['required','in:approve,request_changes,reject,unpublish,archive'],'note'=>['nullable','string','max:5000'],'scheduled_at'=>['nullable','date','after:now']]);$old=$publication->status;
        if($data['decision']==='approve'){
            if(!empty($data['scheduled_at'])){$publication->update(['status'=>'scheduled','scheduled_at'=>$data['scheduled_at'],'moderation_note'=>$data['note']??null,'moderated_by'=>$request->user()->id]);$publication->document->update(['status'=>'review']);}
            else{$moderation->publish($publication,$request->user(),$data['note']??null);$citations->rebuild($publication->fresh());}
        }else{
            $status=match($data['decision']){'request_changes'=>'changes_requested','reject'=>'rejected','unpublish'=>'unpublished','archive'=>'archived'};
            $publication->update(['status'=>$status,'moderation_note'=>$data['note']??null,'moderated_by'=>$request->user()->id,'scheduled_at'=>null]);$publication->document->update(['status'=>$status]);
        }
        AuditLog::log('knowledge_publication_moderated',$request->user()->id,KnowledgePublication::class,$publication->id,$old,$publication->fresh()->status,$request->ip(),$request->userAgent(),'status');
        return back()->with('success','Moderation decision recorded.');
    }

    public function duplicate(Request $request, KnowledgePublication $publication, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view',$publication);abort_unless($publication->creator_id===$request->user()->id||$request->user()->isAdmin(),403);
        $copy=DB::transaction(function()use($publication,$request,$workspace){$publication->load('document','tags');$doc=$workspace->create(['document_type'=>'knowledge_publication','title'=>$publication->title.' (Copy)','body'=>$publication->document->body,'status'=>'draft','visibility'=>'private'],$request->user());$copy=$publication->replicate(['uuid','slug','status','submitted_at','scheduled_at','published_at','moderated_by','moderation_report_id','view_count','bookmark_count','comment_count','share_count','download_count','featured_at','pinned_at']);$copy->creator_id=$request->user()->id;$copy->university_id=$request->user()->university_id;$copy->department_id=$request->user()->department_id;$copy->content_document_id=$doc->id;$copy->title=$publication->title.' (Copy)';$copy->status='draft';$copy->visibility='private';$copy->save();$copy->tags()->sync($publication->tags->pluck('id'));return $copy;});
        return redirect()->route('knowledge.manage.edit',$copy)->with('success','Publication duplicated as a private draft.');
    }

    public function restoreVersion(Request $request, KnowledgePublication $publication, ContentVersion $version, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('update',$publication);$workspace->restore($publication->document,$version,$request->user());$publication->update(['status'=>'draft']);return back()->with('success','Selected version restored as a new version.');
    }

    public function feature(Request $request, KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('moderate',$publication);$data=$request->validate(['action'=>['required','in:feature,unfeature,pin,unpin']]);$publication->update(match($data['action']){'feature'=>['featured_at'=>now()],'unfeature'=>['featured_at'=>null],'pin'=>['pinned_at'=>now()],'unpin'=>['pinned_at'=>null]});return back()->with('success','Publication placement updated.');
    }

    public function bookmark(Request $request, KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('view',$publication);$bookmark=$publication->bookmarks()->where('user_id',$request->user()->id)->first();if($bookmark){$bookmark->delete();$publication->whereKey($publication->id)->where('bookmark_count','>',0)->decrement('bookmark_count');$message='Bookmark removed.';}else{$publication->bookmarks()->create(['user_id'=>$request->user()->id,'created_at'=>now()]);$publication->increment('bookmark_count');$message='Publication bookmarked.';}return back()->with('success',$message);
    }

    public function destroy(KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('delete',$publication);$publication->update(['status'=>'archived']);$publication->delete();return redirect()->route('knowledge.manage')->with('success','Publication moved to archive.');
    }

    public function forceDestroy(Request $request, string $publication): RedirectResponse
    {
        $model=KnowledgePublication::withTrashed()->where('slug',$publication)->firstOrFail();abort_unless($request->user()->isSuperAdmin(),403);abort_unless($model->trashed(),422,'Archive the publication before permanent deletion.');$model->forceDelete();return back()->with('success','Publication permanently deleted by a super administrator.');
    }

    protected function validated(Request $request): array
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'body'=>['required','string','max:1000000'],'excerpt'=>['nullable','string','max:2000'],'category_id'=>['nullable','integer','exists:knowledge_categories,id'],'content_type'=>['required','in:academic_article,research_output,research_insight,study_guide,exam_preparation,tutorial,programming_tutorial,campus_guide,career_guide,siwes_experience,project_experience,educational_video,presentation,reference_material,case_study,announcement,digital_resource,question_bank,mock_exam,template,ebook,video_course'],'language'=>['nullable','string','max:10'],'doi'=>['nullable','string','max:255'],'copyright'=>['nullable','string','max:255'],'visibility'=>['required','in:public,institution'],'access_type'=>['required','in:free,premium,institution'],'price'=>['nullable','numeric','min:0'],'tags'=>['nullable','string','max:1000']]);
        $data['body'] = app(RichTextSanitizer::class)->sanitize($data['body']);
        abort_if(trim(strip_tags($data['body'])) === '', 422, 'Publication content cannot be empty.');
        if(!empty($data['category_id'])){$category=KnowledgeCategory::findOrFail($data['category_id']);abort_unless($category->university_id===null||$request->user()->isSuperAdmin()||$category->university_id===$request->user()->university_id,403);}return $data;
    }

    protected function categoriesFor(Request $request){return KnowledgeCategory::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('university_id')->orWhere('university_id',$request->user()->university_id))->orderBy('name')->get();}
    protected function syncTags(KnowledgePublication $publication,string $tags):void{$ids=collect(explode(',',$tags))->map(fn($t)=>trim($t))->filter()->unique(fn($t)=>Str::lower($t))->take(15)->map(function($name){$slug=Str::slug($name);return KnowledgeTag::firstOrCreate(['slug'=>$slug],['name'=>$name])->id;});$publication->tags()->sync($ids->all());}
    private function wordCount(string $body):int{return str_word_count(strip_tags($body));}
    private function readingTime(string $body):int{return max(1,(int)ceil($this->wordCount($body)/220));}
}
