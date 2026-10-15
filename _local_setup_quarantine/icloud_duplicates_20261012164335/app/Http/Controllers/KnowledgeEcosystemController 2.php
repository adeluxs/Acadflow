<?php

namespace App\Http\Controllers;

use App\Models\AcademicCertificate;
use App\Models\AcademicChallenge;
use App\Models\AcademicChallengeEntry;
use App\Models\AcademicEvent;
use App\Models\AcademicEventInvitation;
use App\Models\AcademicEventRegistration;
use App\Models\AiGroundingSession;
use App\Models\KnowledgeCommunity;
use App\Models\KnowledgeCommunityInvitation;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgeTag;
use App\Models\Group;
use App\Models\KnowledgeCommunityPost;
use App\Models\KnowledgePublication;
use App\Models\KnowledgePollOption;
use App\Models\LearningEnrollment;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\ReadingList;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\Ai\GroundedCompanionService;
use App\Services\Ai\GroundedQuestionIntelligenceService;
use App\Services\Discovery\DiscoverySearchService;
use App\Services\Discovery\RecommendationService;
use App\Services\EngagementService;
use App\Services\Knowledge\CitationNetworkService;
use App\Services\Knowledge\CommunityService;
use App\Services\Knowledge\CreatorService;
use App\Services\Knowledge\EventChallengeService;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\Knowledge\LearningPathService;
use App\Services\Knowledge\OrcidService;
use App\Services\Knowledge\ReadingListService;
use App\Services\Reputation\ReputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class KnowledgeEcosystemController extends Controller
{
    public function search(Request $request, DiscoverySearchService $search, RecommendationService $recommendations): View
    {
        $data = $request->validate(['q' => ['nullable','string','max:500'], 'type' => ['nullable','string','max:120'], 'university_id' => ['nullable','integer'], 'semantic' => ['nullable','boolean']]);
        $results = $search->search((string) ($data['q'] ?? ''), $request->user(), array_filter(['content_type' => $data['type'] ?? null, 'university_id' => $data['university_id'] ?? null]), 40);
        $recommended = $request->user() ? $recommendations->forUser($request->user(), 12) : collect();
        return view('knowledge.search', compact('results', 'recommended'));
    }

    public function creator(User $creator, ReputationService $reputation): View
    {
        $creator->load(['creatorProfile','reputationProfile','university','department']);
        abort_if(! $creator->creatorProfile?->is_public && auth()->id() !== $creator->id && ! auth()->user()?->isAdmin(), 404);
        if (! $creator->reputationProfile || ! $creator->reputationProfile->calculated_at || $creator->reputationProfile->calculated_at->lt(now()->subHour())) {
            $creator->setRelation('reputationProfile', $reputation->recalculate($creator));
        }
        $publications = $creator->knowledgePublications()->with(['category','tags'])->where('status','published')->latest('published_at')->paginate(12);
        return view('knowledge.creator', compact('creator','publications'));
    }

    public function editCreator(Request $request): View
    {
        return view('knowledge.creator-edit', ['profile' => $request->user()->creatorProfile]);
    }

    public function updateCreator(Request $request, CreatorService $creators): RedirectResponse
    {
        $data = $request->validate([
            'headline'=>['nullable','string','max:255'], 'biography'=>['nullable','string','max:5000'], 'expertise'=>['nullable','string','max:1000'],
            'position'=>['nullable','string','max:255'], 'orcid'=>['nullable','regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'], 'website'=>['nullable','url','max:500'],
            'is_public'=>['nullable','boolean'], 'show_institution'=>['nullable','boolean'], 'show_department'=>['nullable','boolean'], 'show_impact'=>['nullable','boolean'], 'personalized_recommendations'=>['nullable','boolean'],
        ]);
        $data['privacy_settings'] = ['show_institution'=>(bool)($data['show_institution']??false), 'show_department'=>(bool)($data['show_department']??false), 'show_impact'=>(bool)($data['show_impact']??false), 'personalized_recommendations'=>(bool)($data['personalized_recommendations']??true)];
        $creators->updateProfile($request->user(), $data);
        return back()->with('success','Creator profile updated.');
    }

    public function syncOrcid(Request $request, OrcidService $orcid): RedirectResponse
    {
        $orcid->sync($request->user());
        return back()->with('success', 'ORCID public profile and work summary synchronized with provenance.');
    }

    public function requestVerification(Request $request, CreatorService $creators): RedirectResponse
    {
        $data=$request->validate(['verification_type'=>['required','in:lecturer,professor,researcher,student_ambassador,department,faculty,university,organization,institution_partner'],'statement'=>['nullable','string','max:5000'],'evidence'=>['nullable','array']]);
        $creators->requestVerification($request->user(),$data);
        return back()->with('success','Verification request submitted for review.');
    }

    public function reviewVerification(Request $request, VerificationRequest $verification, CreatorService $creators): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(),403);
        if (! $request->user()->isSuperAdmin()) abort_unless($verification->university_id === $request->user()->university_id,403);
        $data=$request->validate(['decision'=>['required','in:approve,reject,suspend,revoke'],'note'=>['nullable','string','max:5000']]);
        $creators->review($verification,$request->user(),$data['decision'],$data['note']??null);
        return back()->with('success','Verification status updated.');
    }

    public function leaderboard(Request $request): View
    {
        $profiles=\App\Models\ReputationProfile::query()->with('user.creatorProfile')->when(! $request->user()?->isSuperAdmin(),fn($q)=>$request->user()?$q->where('university_id',$request->user()->university_id):$q)->orderByDesc('overall_score')->paginate(50);
        return view('knowledge.leaderboard',compact('profiles'));
    }

    public function communities(Request $request): View
    {
        $filters=$request->validate(['q'=>['nullable','string','max:255'],'type'=>['nullable','string','max:60'],'visibility'=>['nullable','in:public,institution,private'],'membership'=>['nullable','in:mine,discover']]);
        $communities=KnowledgeCommunity::query()->with(['owner','category','tags'])->withCount(['members'=>fn($q)=>$q->where('status','active'),'posts'=>fn($q)=>$q->where('status','published')])->where('status','active')
            ->where(function($q)use($request){$q->where('visibility','public');if($request->user()){if($request->user()->university_id)$q->orWhere(fn($i)=>$i->where('visibility','institution')->where('university_id',$request->user()->university_id));$q->orWhereHas('members',fn($m)=>$m->where('user_id',$request->user()->id)->where('status','active'));}})
            ->when($filters['q']??null,fn($q,$term)=>$q->where(fn($s)=>$s->where('name','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')))
            ->when($filters['type']??null,fn($q,$type)=>$q->where('community_type',$type))
            ->when($filters['visibility']??null,fn($q,$visibility)=>$q->where('visibility',$visibility))
            ->when(($filters['membership']??null)==='mine'&&$request->user(),fn($q)=>$q->whereHas('members',fn($m)=>$m->where('user_id',$request->user()->id)->where('status','active')))
            ->orderByDesc('member_count')->latest()->paginate(24)->withQueryString();
        return view('knowledge.communities.index',compact('communities','filters'));
    }

    public function createCommunity(Request $request): View
    {
        $this->authorize('create',KnowledgeCommunity::class);
        $categories=KnowledgeCategory::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('university_id')->when($request->user()->university_id,fn($s)=>$s->orWhere('university_id',$request->user()->university_id)))->orderBy('name')->get();
        $tags=KnowledgeTag::query()->orderBy('name')->limit(250)->get();
        return view('knowledge.communities.form',compact('categories','tags'));
    }

    public function storeCommunity(Request $request, CommunityService $service, MediaSecurityService $media): RedirectResponse
    {
        $this->authorize('create',KnowledgeCommunity::class);
        $data=$request->validate([
            'name'=>['required','string','max:255'],'description'=>['required','string','max:5000'],'community_type'=>['required','in:department,faculty,course,research_area,discipline,technology,professional_interest'],
            'visibility'=>['required','in:public,institution,private'],'membership_mode'=>['required','in:open,approval,closed'],'requires_moderation'=>['nullable','boolean'],'category_id'=>['nullable','integer','exists:knowledge_categories,id'],
            'cover_media_id'=>['nullable','integer','exists:media_assets,id'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192'],'tag_ids'=>['nullable','array','max:20'],'tag_ids.*'=>['integer','exists:knowledge_tags,id'],'rules_text'=>['nullable','string','max:5000'],'status'=>['nullable','in:active,draft'],
        ]);
        $data['rules']=collect(preg_split('/\r\n|\r|\n/',$data['rules_text']??''))->map(fn($line)=>trim($line))->filter()->values()->all();
        unset($data['cover_image']);
        $community=$service->create($request->user(),$data);
        if ($request->hasFile('cover_image')) {
            $asset = $media->store($request->file('cover_image'), $request->user(), $community, $community->visibility === 'public' ? 'public' : ($community->visibility === 'institution' ? 'institution' : 'private'), ['purpose' => 'community_cover']);
            $community->update(['cover_media_id' => $asset->id]);
        }
        return redirect()->route('knowledge.communities.show',$community)->with('success','Community created.');
    }

    public function community(Request $request, KnowledgeCommunity $community, CommunityService $service): View
    {
        $service->ensureVisible($community,$request->user());
        $community->load(['owner','category','tags','coverMedia','members'=>fn($q)=>$q->with('user')->whereIn('status',['active','pending']),'invitations.invitee']);
        $posts=$community->posts()->with(['author','document','pollOptions.votes'])->where(function($q)use($request,$community){$q->where('status','published');if($request->user()?->can('moderate',$community))$q->orWhere('status','pending');})->orderByDesc('is_pinned')->latest()->paginate(20);
        return view('knowledge.communities.show',compact('community','posts'));
    }

    public function editCommunity(Request $request, KnowledgeCommunity $community): View
    {
        $this->authorize('update',$community);
        $categories=KnowledgeCategory::query()->where('is_active',true)->orderBy('name')->get();$tags=KnowledgeTag::query()->orderBy('name')->limit(250)->get();
        return view('knowledge.communities.form',compact('community','categories','tags'));
    }

    public function updateCommunity(Request $request, KnowledgeCommunity $community, CommunityService $service, MediaSecurityService $media): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'description'=>['required','string','max:5000'],'community_type'=>['required','in:department,faculty,course,research_area,discipline,technology,professional_interest'],'visibility'=>['required','in:public,institution,private'],'membership_mode'=>['required','in:open,approval,closed'],'requires_moderation'=>['nullable','boolean'],'category_id'=>['nullable','integer','exists:knowledge_categories,id'],'cover_media_id'=>['nullable','integer','exists:media_assets,id'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192'],'tag_ids'=>['nullable','array','max:20'],'tag_ids.*'=>['integer','exists:knowledge_tags,id'],'rules_text'=>['nullable','string','max:5000'],'status'=>['required','in:active,draft,archived']]);
        $data['rules']=collect(preg_split('/\r\n|\r|\n/',$data['rules_text']??''))->map(fn($line)=>trim($line))->filter()->values()->all();
        unset($data['cover_image']);
        if ($request->hasFile('cover_image')) {
            $asset = $media->store($request->file('cover_image'), $request->user(), $community, ($data['visibility'] ?? $community->visibility) === 'public' ? 'public' : (($data['visibility'] ?? $community->visibility) === 'institution' ? 'institution' : 'private'), ['purpose' => 'community_cover']);
            $data['cover_media_id'] = $asset->id;
        }
        $service->update($community,$request->user(),$data);
        return redirect()->route('knowledge.communities.show',$community)->with('success','Community updated.');
    }

    public function reportCommunity(Request $request, KnowledgeCommunity $community, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view', $community);
        $data = $request->validate([
            'reason' => ['required', 'in:spam,harassment,misinformation,privacy,policy,other'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);
        $engagement->report($community, $request->user(), $data['reason'], $data['details'] ?? null);

        return back()->with('success', 'Community report submitted for human moderation.');
    }

    public function archiveCommunity(Request $request, KnowledgeCommunity $community, CommunityService $service): RedirectResponse
    {
        $service->archive($community,$request->user());return redirect()->route('knowledge.communities.index')->with('success','Community archived.');
    }

    public function joinCommunity(Request $request, KnowledgeCommunity $community, CommunityService $service): RedirectResponse
    {
        $status=$service->join($community,$request->user());return back()->with('success',$status==='active'?'You joined the community.':'Membership request submitted.');
    }

    public function leaveCommunity(Request $request, KnowledgeCommunity $community, CommunityService $service): RedirectResponse
    {
        $service->leave($community,$request->user());return redirect()->route('knowledge.communities.index')->with('success','You left the community.');
    }

    public function inviteCommunityMember(Request $request, KnowledgeCommunity $community, CommunityService $service): RedirectResponse
    {
        $data=$request->validate(['user_id'=>['required','integer','exists:users,id'],'role'=>['required','in:member,moderator,administrator']]);$service->invite($community,$request->user(),User::findOrFail($data['user_id']),$data['role']);return back()->with('success','Community invitation sent.');
    }

    public function respondCommunityInvitation(Request $request, KnowledgeCommunityInvitation $invitation, CommunityService $service): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:accept,decline']]);$service->respondToInvitation($invitation,$request->user(),$data['decision']==='accept');return redirect()->route('knowledge.communities.show',$invitation->community)->with('success','Invitation response recorded.');
    }

    public function moderateCommunityMember(Request $request, KnowledgeCommunity $community, User $member, CommunityService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:active,pending,suspended,removed'],'role'=>['required','in:member,moderator,administrator']]);$service->moderateMember($community,$request->user(),$member,$data['status'],$data['role']);return back()->with('success','Community member updated.');
    }

    public function postCommunity(Request $request, KnowledgeCommunity $community, CommunityService $service): RedirectResponse
    {
        // Empty poll inputs are intentionally ignored for every non-poll post.
        // This keeps the composer flexible and prevents blank hidden poll fields
        // from making poll validation fire for discussions/resources/events.
        if ($request->input('post_type') === 'poll') {
            $request->merge([
                'poll_options' => collect($request->input('poll_options', []))
                    ->map(fn ($option) => trim((string) $option))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'post_type' => ['required', 'in:discussion,announcement,poll,resource,event'],
            'poll_options' => ['exclude_unless:post_type,poll', 'required', 'array', 'min:2', 'max:10'],
            'poll_options.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);

        $post = $service->post($community, $request->user(), $data);

        return back()->with('success', $post->status === 'published'
            ? 'Community post published.'
            : 'Post submitted for moderation.');
    }

    public function moderateCommunityPost(Request $request, KnowledgeCommunityPost $post, CommunityService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:published,rejected,hidden'],'is_pinned'=>['nullable','boolean']]);$service->moderatePost($post,$request->user(),$data['status'],$request->boolean('is_pinned'));return back()->with('success','Community post reviewed.');
    }

    public function votePoll(Request $request, KnowledgePollOption $option, CommunityService $service): RedirectResponse
    {
        $service->votePoll($option,$request->user());return back()->with('success','Poll vote recorded.');
    }

    public function learningPaths(Request $request): View
    {
        $paths=LearningPath::query()->with(['creator'])->withCount('items')->where(function($q)use($request){$q->where('status','published')->where(function($v)use($request){$v->where('visibility','public');if($request->user())$v->orWhere('university_id',$request->user()->university_id);});if($request->user())$q->orWhere('creator_id',$request->user()->id);})->latest()->paginate(24);
        return view('knowledge.learning.index',compact('paths'));
    }

    public function storeLearningPath(Request $request, LearningPathService $service): RedirectResponse
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'visibility'=>['required','in:public,institution,private'],'access_type'=>['required','in:free,premium,institution'],'price'=>['nullable','regex:/^\d+(?:\.\d{1,2})?$/'],'status'=>['required','in:draft,published'],'certificate_enabled'=>['nullable','boolean'],'outcomes'=>['nullable','string','max:2000']]);
        $path=$service->create($request->user(),$data);
        return redirect()->route('knowledge.learning.show',$path)->with('success','Learning path created.');
    }

    public function learningPath(Request $request, LearningPath $path): View
    {
        abort_unless($path->status==='published'||$request->user()?->id===$path->creator_id||$request->user()?->isAdmin(),404);
        if($path->visibility!=='public')abort_unless($request->user()&&($request->user()->isSuperAdmin()||$request->user()->university_id===$path->university_id),403);
        $path->load(['creator','items.item']);
        $enrollment=$request->user()?$path->enrollments()->with('progressRecords')->where('user_id',$request->user()->id)->first():null;
        $hasAccess=$path->access_type!=='premium'||($request->user()&&($path->creator_id===$request->user()->id||$request->user()->isAdmin()||$request->user()->hasEntitlement($path)));
        $gateways=\App\Models\PaymentGateway::query()->where('is_active',true)->orderBy('sort_order')->get();
        return view('knowledge.learning.show',compact('path','enrollment','hasAccess','gateways'));
    }

    public function addLearningItem(Request $request, LearningPath $path, LearningPathService $service): RedirectResponse
    {
        $data=$request->validate(['item_kind'=>['required','in:publication,course_material,assignment,quiz,external'],'item_id'=>['nullable','integer'],'title'=>['required','string','max:255'],'description'=>['nullable','string','max:3000'],'position'=>['nullable','integer','min:1'],'is_required'=>['nullable','boolean'],'estimated_minutes'=>['nullable','integer','min:0','max:100000'],'url'=>['nullable','url','max:2000']]);
        $data['item_type']=match($data['item_kind']){'publication'=>KnowledgePublication::class,'course_material'=>\App\Models\CourseMaterial::class,'assignment'=>\App\Models\SubmissionTask::class,'quiz'=>'quiz','external'=>'external_link'};
        $data['settings']=array_filter(['url'=>$data['url']??null]);unset($data['item_kind'],$data['url']);
        $service->addItem($path,$request->user(),$data);
        return back()->with('success','Learning item added.');
    }

    public function enrollLearning(Request $request, LearningPath $path, LearningPathService $service): RedirectResponse
    {
        $service->enroll($path,$request->user());
        return back()->with('success','Enrollment active.');
    }

    public function updateLearningProgress(Request $request, LearningEnrollment $enrollment, LearningPathItem $item, LearningPathService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:not_started,in_progress,completed'],'score'=>['nullable','numeric','min:0','max:100'],'time_spent_seconds'=>['nullable','integer','min:0'],'state'=>['nullable','array']]);
        $service->markProgress($enrollment,$item,$request->user(),$data);
        return back()->with('success','Learning progress saved.');
    }

    public function readingLists(Request $request): View
    {
        $lists=ReadingList::query()->withCount('items')->where(function($q)use($request){$q->where('visibility','public');if($request->user())$q->orWhere('owner_id',$request->user()->id)->orWhereHas('members',fn($m)=>$m->where('user_id',$request->user()->id));})->latest()->paginate(24);
        return view('knowledge.reading.index',compact('lists'));
    }

    public function storeReadingList(Request $request, ReadingListService $service): RedirectResponse
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'research_project_id'=>['nullable','integer','exists:research_projects,id'],'course_id'=>['nullable','integer','exists:courses,id'],'list_type'=>['required','in:private,public,course,research,department,collaborative'],'visibility'=>['required','in:private,public,institution'],'is_collaborative'=>['nullable','boolean']]);
        $list=$service->create($request->user(),$data);
        return redirect()->route('knowledge.reading.show',$list)->with('success','Reading list created.');
    }

    public function readingList(Request $request, ReadingList $list, ReadingListService $service): View
    {
        $service->authorizeView($list,$request->user());$list->load(['owner','items.item','members.user','researchProject']);
        return view('knowledge.reading.show',compact('list'));
    }

    public function addReadingItem(Request $request, ReadingList $list, ReadingListService $service): RedirectResponse
    {
        $data=$request->validate(['publication_id'=>['required','integer','exists:knowledge_publications,id'],'note'=>['nullable','string','max:3000']]);
        $publication=KnowledgePublication::findOrFail($data['publication_id']);$this->authorize('view',$publication);
        $service->add($list,$publication,$request->user(),$data['note']??null);
        return back()->with('success','Publication added to reading list and research references where applicable.');
    }

    public function updateReadingItem(Request $request, ReadingList $list, \App\Models\ReadingListItem $item): RedirectResponse
    {
        abort_unless($item->reading_list_id===$list->id,404);abort_unless($list->owner_id===$request->user()->id||$request->user()->isAdmin()||$list->members()->where('user_id',$request->user()->id)->where('role','editor')->exists(),403);
        $data=$request->validate(['status'=>['required','in:unread,reading,completed'],'note'=>['nullable','string','max:3000']]);$item->update($data+['completed_at'=>$data['status']==='completed'?now():null]);
        return back()->with('success','Reading progress updated.');
    }

    public function syncReadingMember(Request $request, ReadingList $list, ReadingListService $service): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', 'in:viewer,editor']]);
        $service->syncMember($list, $request->user(), User::findOrFail($data['user_id']), $data['role']);
        return back()->with('success', 'Reading-list collaborator updated.');
    }

    public function removeReadingMember(Request $request, ReadingList $list, User $member, ReadingListService $service): RedirectResponse
    {
        $service->removeMember($list, $request->user(), $member);
        return back()->with('success', 'Reading-list collaborator removed.');
    }

    public function exportReadingList(Request $request, ReadingList $list, ReadingListService $service)
    {
        $rows = $service->exportRows($list, $request->user());
        $format = $request->validate(['format' => ['nullable', 'in:csv,json']])['format'] ?? 'csv';
        if ($format === 'json') return response()->json(['list' => $list->only(['uuid','title','description','list_type','visibility']), 'items' => $rows]);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Position', 'Title', 'Type', 'Status', 'Note', 'Completed At', 'URL']);
            foreach ($rows as $row) fputcsv($handle, array_values($row));
            fclose($handle);
        }, str($list->title)->slug().'-reading-list.csv', ['Content-Type' => 'text/csv']);
    }

    public function events(Request $request): View
    {
        $filters=$request->validate(['q'=>['nullable','string','max:255'],'type'=>['nullable','string','max:50'],'format'=>['nullable','in:online,physical,hybrid'],'period'=>['nullable','in:upcoming,ongoing,past,mine']]);
        $events=AcademicEvent::query()->with(['organizer','community','group','category'])->withCount(['registrations'=>fn($q)=>$q->whereIn('status',['registered','attended'])])->whereIn('status',['published','ongoing','completed'])
            ->where(function($q)use($request){$q->where('visibility','public');if($request->user()){if($request->user()->university_id)$q->orWhere(fn($i)=>$i->where('visibility','institution')->where('university_id',$request->user()->university_id));$q->orWhere('organizer_id',$request->user()->id)->orWhereHas('registrations',fn($r)=>$r->where('user_id',$request->user()->id));}})
            ->when($filters['q']??null,fn($q,$term)=>$q->where(fn($s)=>$s->where('title','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')->orWhere('location','like','%'.$term.'%')))
            ->when($filters['type']??null,fn($q,$type)=>$q->where('event_type',$type))->when($filters['format']??null,fn($q,$format)=>$q->where('format',$format))
            ->when(($filters['period']??'upcoming')==='upcoming',fn($q)=>$q->where('starts_at','>',now()))->when(($filters['period']??null)==='ongoing',fn($q)=>$q->where('starts_at','<=',now())->where(fn($e)=>$e->whereNull('ends_at')->orWhere('ends_at','>=',now())))
            ->when(($filters['period']??null)==='past',fn($q)=>$q->where(fn($e)=>$e->where('ends_at','<',now())->orWhere(fn($n)=>$n->whereNull('ends_at')->where('starts_at','<',now()))))
            ->when(($filters['period']??null)==='mine'&&$request->user(),fn($q)=>$q->where(fn($m)=>$m->where('organizer_id',$request->user()->id)->orWhereHas('registrations',fn($r)=>$r->where('user_id',$request->user()->id))))
            ->orderBy('starts_at')->paginate(24)->withQueryString();
        return view('knowledge.events.index',compact('events','filters'));
    }

    public function createEventForm(Request $request): View
    {
        $this->authorize('create',AcademicEvent::class);$categories=KnowledgeCategory::where('is_active',true)->orderBy('name')->get();$tags=KnowledgeTag::orderBy('name')->limit(250)->get();$communities=KnowledgeCommunity::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$groups=Group::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$users=User::query()->when($request->user()->university_id,fn($q)=>$q->where('university_id',$request->user()->university_id))->where('id','!=',$request->user()->id)->orderBy('first_name')->limit(500)->get();
        return view('knowledge.events.form',compact('categories','tags','communities','groups','users'));
    }

    public function storeEvent(Request $request, EventChallengeService $service, MediaSecurityService $media): RedirectResponse
    {
        $this->authorize('create',AcademicEvent::class);$data=$this->validateEvent($request);unset($data['cover_image']);$event=$service->createEvent($request->user(),$data);if($request->hasFile('cover_image')){$asset=$media->store($request->file('cover_image'),$request->user(),$event,$event->visibility==='public'?'public':($event->visibility==='institution'?'institution':'private'),['purpose'=>'event_cover']);$event->update(['cover_media_id'=>$asset->id]);}return redirect()->route('knowledge.events.show',$event)->with('success','Academic event created.');
    }

    public function event(Request $request, AcademicEvent $event, EngagementService $engagement): View
    {
        $this->authorize('view',$event);
        $event->load(['organizer','coOrganizers','coverMedia','community','group','category','tags','reminders','registrations.user','invitations.invitee']);
        $registration=$request->user()?$event->registrations->firstWhere('user_id',$request->user()->id):null;
        $comments=$engagement->commentsFor($event,20);
        return view('knowledge.events.show',compact('event','registration','comments'));
    }

    public function editEvent(Request $request, AcademicEvent $event): View
    {
        $this->authorize('update',$event);$categories=KnowledgeCategory::where('is_active',true)->orderBy('name')->get();$tags=KnowledgeTag::orderBy('name')->limit(250)->get();$communities=KnowledgeCommunity::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$groups=Group::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$users=User::query()->when($request->user()->university_id,fn($q)=>$q->where('university_id',$request->user()->university_id))->where('id','!=',$request->user()->id)->orderBy('first_name')->limit(500)->get();return view('knowledge.events.form',compact('event','categories','tags','communities','groups','users'));
    }

    public function updateEvent(Request $request, AcademicEvent $event, EventChallengeService $service, MediaSecurityService $media): RedirectResponse
    {
        $data = $this->validateEvent($request, false);
        unset($data['cover_image']);

        if ($request->hasFile('cover_image')) {
            $visibility = $data['visibility'] ?? $event->visibility;
            $asset = $media->store(
                $request->file('cover_image'),
                $request->user(),
                $event,
                $visibility === 'public' ? 'public' : ($visibility === 'institution' ? 'institution' : 'private'),
                ['purpose' => 'event_cover']
            );
            $data['cover_media_id'] = $asset->id;
        }

        $service->updateEvent($event, $request->user(), $data);

        return redirect()->route('knowledge.events.show', $event)->with('success', 'Event updated.');
    }

    public function changeEventStatus(Request $request, AcademicEvent $event, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:draft,published,cancelled,completed'],'reason'=>['nullable','required_if:status,cancelled','string','max:2000']]);$service->changeEventStatus($event,$request->user(),$data['status'],$data['reason']??null);return back()->with('success','Event status updated.');
    }

    public function deleteEvent(Request $request, AcademicEvent $event, EventChallengeService $service): RedirectResponse
    {
        $service->deleteEvent($event,$request->user());return redirect()->route('knowledge.events.index')->with('success','Event deleted safely.');
    }

    public function registerEvent(Request $request, AcademicEvent $event, EventChallengeService $service): RedirectResponse
    {
        $registration=$service->register($event,$request->user());$message=match($registration->status){'pending'=>'Your registration request was submitted for approval.','waitlisted'=>'You were added to the waitlist.',default=>'Event registration confirmed.'};return back()->with('success',$message);
    }

    public function unregisterEvent(Request $request, AcademicEvent $event, EventChallengeService $service): RedirectResponse
    {
        $service->unregister($event,$request->user());return back()->with('success','Event registration cancelled.');
    }

    public function attendEvent(Request $request, AcademicEvent $event, User $attendee, EventChallengeService $service): RedirectResponse
    {
        $service->markAttendance($event,$attendee,$request->user());return back()->with('success','Attendance recorded.');
    }

    public function reviewEventRegistration(Request $request, AcademicEvent $event, AcademicEventRegistration $registration, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject']]);
        $service->reviewRegistration($event,$registration,$request->user(),$data['decision']==='approve');
        return back()->with('success','Registration request reviewed.');
    }

    public function inviteEventAttendee(Request $request, AcademicEvent $event, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['user_id'=>['required','integer','exists:users,id']]);
        $service->inviteEventAttendee($event,$request->user(),User::findOrFail($data['user_id']));
        return back()->with('success','Event invitation sent.');
    }

    public function respondEventInvitation(Request $request, AcademicEventInvitation $invitation, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:accept,decline']]);
        $registration=$service->respondEventInvitation($invitation,$request->user(),$data['decision']==='accept');
        return redirect()->route('knowledge.events.show',$invitation->event)->with('success',$registration?'Invitation accepted and RSVP recorded.':'Invitation declined.');
    }

    public function commentEvent(Request $request, AcademicEvent $event, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$event);
        $data=$request->validate(['body'=>['required','string','max:10000'],'parent_id'=>['nullable','integer','exists:engagement_comments,id']]);
        $engagement->comment($event,$request->user(),$data['body'],['parent_id'=>$data['parent_id']??null,'university_id'=>$event->university_id,'visibility'=>$event->visibility,'thread_title'=>$event->title]);
        return back()->with('success','Event discussion message posted.');
    }

    public function reportEvent(Request $request, AcademicEvent $event, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$event);
        $data=$request->validate(['reason'=>['required','in:spam,misleading,abuse,policy'],'details'=>['nullable','string','max:5000']]);
        $engagement->report($event,$request->user(),$data['reason'],$data['details']??null);
        return back()->with('success','Event report submitted for moderator review.');
    }

    public function challenges(Request $request): View
    {
        $filters=$request->validate(['q'=>['nullable','string','max:255'],'type'=>['nullable','string','max:50'],'mode'=>['nullable','in:individual,team,both'],'status'=>['nullable','in:upcoming,active,judging,completed,mine']]);
        $challenges=AcademicChallenge::query()->with(['organizer','community','group','category'])->withCount(['entries'=>fn($q)=>$q->where('is_final',true)])->whereIn('status',['published','active','judging','completed'])
            ->where(function($q)use($request){$q->where('visibility','public');if($request->user()){if($request->user()->university_id)$q->orWhere(fn($i)=>$i->where('visibility','institution')->where('university_id',$request->user()->university_id));$q->orWhere('organizer_id',$request->user()->id)->orWhereHas('entries',fn($e)=>$e->where('user_id',$request->user()->id));}})
            ->when($filters['q']??null,fn($q,$term)=>$q->where(fn($s)=>$s->where('title','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')))->when($filters['type']??null,fn($q,$type)=>$q->where('challenge_type',$type))->when($filters['mode']??null,fn($q,$mode)=>$q->where('participation_mode',$mode))
            ->when(($filters['status']??null)==='upcoming',fn($q)=>$q->where('starts_at','>',now()))->when(($filters['status']??null)==='active',fn($q)=>$q->whereIn('status',['published','active'])->where('starts_at','<=',now())->where('submission_deadline','>=',now()))->when(($filters['status']??null)==='judging',fn($q)=>$q->where('status','judging'))->when(($filters['status']??null)==='completed',fn($q)=>$q->where('status','completed'))->when(($filters['status']??null)==='mine'&&$request->user(),fn($q)=>$q->where(fn($m)=>$m->where('organizer_id',$request->user()->id)->orWhereHas('entries',fn($e)=>$e->where('user_id',$request->user()->id))))
            ->orderBy('submission_deadline')->paginate(24)->withQueryString();return view('knowledge.challenges.index',compact('challenges','filters'));
    }

    public function createChallengeForm(Request $request): View
    {
        $this->authorize('create',AcademicChallenge::class);$categories=KnowledgeCategory::where('is_active',true)->orderBy('name')->get();$tags=KnowledgeTag::orderBy('name')->limit(250)->get();$communities=KnowledgeCommunity::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$groups=Group::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$users=User::query()->when($request->user()->university_id,fn($q)=>$q->where('university_id',$request->user()->university_id))->where('id','!=',$request->user()->id)->orderBy('first_name')->limit(500)->get();return view('knowledge.challenges.form',compact('categories','tags','communities','groups','users'));
    }

    public function storeChallenge(Request $request, EventChallengeService $service, MediaSecurityService $media): RedirectResponse
    {
        $this->authorize('create',AcademicChallenge::class);$data=$this->validateChallenge($request);unset($data['cover_image']);$challenge=$service->createChallenge($request->user(),$data);if($request->hasFile('cover_image')){$asset=$media->store($request->file('cover_image'),$request->user(),$challenge,$challenge->visibility==='public'?'public':($challenge->visibility==='institution'?'institution':'private'),['purpose'=>'challenge_cover']);$challenge->update(['cover_media_id'=>$asset->id]);}return redirect()->route('knowledge.challenges.show',$challenge)->with('success','Academic challenge created.');
    }

    public function challenge(Request $request, AcademicChallenge $challenge, EngagementService $engagement): View
    {
        $this->authorize('view',$challenge);
        $challenge->load(['organizer','judges','coverMedia','community','group','category','tags','entries.user','entries.teamMembers','entries.document','entries.publication','entries.scores.judge']);
        $myEntry=$request->user()?$challenge->entries->firstWhere('user_id',$request->user()->id):null;
        $comments=$engagement->commentsFor($challenge,20);
        return view('knowledge.challenges.show',compact('challenge','myEntry','comments'));
    }

    public function editChallenge(Request $request, AcademicChallenge $challenge): View
    {
        $this->authorize('update',$challenge);$categories=KnowledgeCategory::where('is_active',true)->orderBy('name')->get();$tags=KnowledgeTag::orderBy('name')->limit(250)->get();$communities=KnowledgeCommunity::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$groups=Group::whereHas('members',fn($q)=>$q->where('user_id',$request->user()->id)->where('status','active'))->get();$users=User::query()->when($request->user()->university_id,fn($q)=>$q->where('university_id',$request->user()->university_id))->where('id','!=',$request->user()->id)->orderBy('first_name')->limit(500)->get();return view('knowledge.challenges.form',compact('challenge','categories','tags','communities','groups','users'));
    }

    public function updateChallenge(Request $request, AcademicChallenge $challenge, EventChallengeService $service, MediaSecurityService $media): RedirectResponse
    {
        $data=$this->validateChallenge($request,false);unset($data['cover_image']);if($request->hasFile('cover_image')){$visibility=$data['visibility']??$challenge->visibility;$asset=$media->store($request->file('cover_image'),$request->user(),$challenge,$visibility==='public'?'public':($visibility==='institution'?'institution':'private'),['purpose'=>'challenge_cover']);$data['cover_media_id']=$asset->id;}$service->updateChallenge($challenge,$request->user(),$data);return redirect()->route('knowledge.challenges.show',$challenge)->with('success','Challenge updated.');
    }

    public function changeChallengeStatus(Request $request, AcademicChallenge $challenge, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:draft,pending_review,published,active,judging,completed,cancelled']]);$service->changeChallengeStatus($challenge,$request->user(),$data['status']);return back()->with('success','Challenge status updated.');
    }

    public function deleteChallenge(Request $request, AcademicChallenge $challenge, EventChallengeService $service): RedirectResponse
    {
        $service->deleteChallenge($challenge,$request->user());return redirect()->route('knowledge.challenges.index')->with('success','Challenge deleted safely.');
    }

    public function submitChallenge(Request $request, AcademicChallenge $challenge, EventChallengeService $service): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:150000'],
            'submission_url' => ['nullable', 'url', 'max:2000'],
            'knowledge_publication_id' => ['nullable', 'integer', 'exists:knowledge_publications,id'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'team_member_ids' => ['nullable', 'array', 'max:50'],
            'team_member_ids.*' => ['integer', 'exists:users,id'],
            'attachment_media_ids' => ['nullable', 'array', 'max:20'],
            'attachment_media_ids.*' => ['integer', 'exists:media_assets,id'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        if (blank($data['body'] ?? null)
            && blank($data['submission_url'] ?? null)
            && empty($data['knowledge_publication_id'])
            && empty($data['attachment_media_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => ['Add written work, a submission link, a publication, or at least one approved attachment.'],
            ]);
        }

        $entry = $service->submit($challenge, $request->user(), $data);

        return back()->with('success', $entry->is_final ? 'Final challenge entry submitted.' : 'Challenge entry saved as a draft.');
    }

    public function judgeChallenge(Request $request, AcademicChallengeEntry $entry, EventChallengeService $service): RedirectResponse
    {
        $data=$request->validate(['scores'=>['required','array','min:1'],'scores.*.score'=>['required','numeric','min:0','max:100'],'scores.*.feedback'=>['nullable','string','max:3000']]);$service->judge($entry,$request->user(),$data['scores']);return back()->with('success','Human judging scores recorded.');
    }

    public function publishChallengeResults(Request $request, AcademicChallenge $challenge, EventChallengeService $service): RedirectResponse
    {
        $service->publishResults($challenge,$request->user());return back()->with('success','Challenge results and rankings published.');
    }

    public function voteChallenge(Request $request, AcademicChallengeEntry $entry, EventChallengeService $service): RedirectResponse
    {
        $active=$service->vote($entry,$request->user());return back()->with('success',$active?'Vote recorded.':'Vote removed.');
    }

    public function commentChallenge(Request $request, AcademicChallenge $challenge, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$challenge);
        $data=$request->validate(['body'=>['required','string','max:10000'],'parent_id'=>['nullable','integer','exists:engagement_comments,id']]);
        $engagement->comment($challenge,$request->user(),$data['body'],['parent_id'=>$data['parent_id']??null,'university_id'=>$challenge->university_id,'visibility'=>$challenge->visibility,'thread_title'=>$challenge->title]);
        return back()->with('success','Challenge discussion message posted.');
    }

    public function reportChallenge(Request $request, AcademicChallenge $challenge, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$challenge);
        $data=$request->validate(['reason'=>['required','in:spam,misleading,academic_integrity,policy'],'details'=>['nullable','string','max:5000']]);
        $engagement->report($challenge,$request->user(),$data['reason'],$data['details']??null);
        return back()->with('success','Challenge report submitted for moderator review.');
    }

    private function validateEvent(Request $request,bool $creating=true): array
    {
        return $request->validate(['title'=>['required','string','max:255'],'description'=>['required','string','max:10000'],'event_type'=>['required','in:seminar,conference,guest_lecture,research_presentation,project_defense,siwes_orientation,workshop,hackathon,competition,training'],'format'=>['required','in:online,physical,hybrid'],'timezone'=>['required','timezone'],'visibility'=>['required','in:public,institution,private'],'status'=>[$creating?'required':'nullable','in:draft,published'],'starts_at'=>['required','date','after:now'],'ends_at'=>['nullable','date','after:starts_at'],'registration_deadline'=>['nullable','date','before_or_equal:starts_at'],'registration_mode'=>['required','in:open,approval,invitation'],'location'=>['nullable','required_if:format,physical,hybrid','string','max:500'],'online_url'=>['nullable','required_if:format,online,hybrid','url','max:2000'],'capacity'=>['nullable','integer','min:1','max:100000'],'waitlist_enabled'=>['nullable','boolean'],'certificate_enabled'=>['nullable','boolean'],'requires_moderation'=>['nullable','boolean'],'knowledge_community_id'=>['nullable','integer','exists:knowledge_communities,id'],'group_id'=>['nullable','integer','exists:groups,id'],'category_id'=>['nullable','integer','exists:knowledge_categories,id'],'cover_media_id'=>['nullable','integer','exists:media_assets,id'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192'],'co_organizer_ids'=>['nullable','array','max:30'],'co_organizer_ids.*'=>['integer','exists:users,id'],'tag_ids'=>['nullable','array','max:20'],'tag_ids.*'=>['integer','exists:knowledge_tags,id'],'reminders'=>['nullable','array','max:10'],'reminders.*'=>['integer','min:0','max:10080']]);
    }

    private function validateChallenge(Request $request,bool $creating=true): array
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'description'=>['required','string','max:10000'],'challenge_type'=>['required','in:writing,research,tutorial,project_documentation,innovation,siwes_report'],'visibility'=>['required','in:public,institution,private'],'participation_mode'=>['required','in:individual,team,both'],'status'=>[$creating?'required':'nullable','in:draft,published'],'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],'submission_deadline'=>['required','date','after_or_equal:starts_at','before_or_equal:ends_at'],'rules_text'=>['nullable','string','max:10000'],'eligibility_rules'=>['nullable','array'],'max_team_members'=>['nullable','required_if:participation_mode,team,both','integer','min:2','max:50'],'judging_criteria'=>['required','array','min:1','max:20'],'judging_criteria.*'=>['nullable','string','max:255'],'rewards_text'=>['nullable','string','max:5000'],'public_voting_enabled'=>['nullable','boolean'],'ai_assistance_enabled'=>['nullable','boolean'],'requires_moderation'=>['nullable','boolean'],'knowledge_community_id'=>['nullable','integer','exists:knowledge_communities,id'],'group_id'=>['nullable','integer','exists:groups,id'],'category_id'=>['nullable','integer','exists:knowledge_categories,id'],'cover_media_id'=>['nullable','integer','exists:media_assets,id'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192'],'judge_ids'=>['nullable','array','max:30'],'judge_ids.*'=>['integer','exists:users,id'],'tag_ids'=>['nullable','array','max:20'],'tag_ids.*'=>['integer','exists:knowledge_tags,id']]);
        $data['rules']=collect(preg_split('/\r\n|\r|\n/',$data['rules_text']??''))->map(fn($line)=>trim($line))->filter()->values()->all();
        $data['rewards']=collect(preg_split('/\r\n|\r|\n/',$data['rewards_text']??''))->map(fn($line)=>trim($line))->filter()->values()->all();
        return $data;
    }

    public function verifyCertificate(string $code): View
    {
        $certificate=AcademicCertificate::query()->with(['user','certifiable'])->where('verification_code',$code)->firstOrFail();
        return view('knowledge.certificate',compact('certificate'));
    }

    public function certificate(AcademicCertificate $certificate, SafeFileDeliveryService $files)
    {
        abort_unless(auth()->id()===$certificate->user_id||auth()->user()?->isAdmin(),403);
        abort_unless($certificate->file_path,404);
        return $files->stream('local', $certificate->file_path, str($certificate->title)->slug().'.html', 'text/html; charset=UTF-8', 'attachment');
    }

    public function citationGraph(KnowledgePublication $publication, CitationNetworkService $citations): View
    {
        $this->authorize('view',$publication);$graph=$citations->graph($publication,3);return view('knowledge.citations',compact('publication','graph'));
    }

    public function rebuildCitations(Request $request, KnowledgePublication $publication, CitationNetworkService $citations): RedirectResponse
    {
        abort_unless($request->user()->id===$publication->creator_id||$request->user()->isAdmin(),403);$count=$citations->rebuild($publication);return back()->with('success',$count.' citation relationships rebuilt.');
    }

    public function syncExternalCitations(Request $request, KnowledgePublication $publication, CitationNetworkService $citations): RedirectResponse
    {
        abort_unless($request->user()->id===$publication->creator_id||$request->user()->isAdmin(),403);$count=$citations->syncExternal($publication);return back()->with('success',$count.' externally sourced citation records stored with provenance.');
    }

    public function citationRankings(Request $request, CitationNetworkService $citations): View
    {
        $rankings=$citations->rankings($request->user()?->isSuperAdmin()?null:$request->user()?->university_id);return view('knowledge.citation-rankings',compact('rankings'));
    }

    public function askCompanion(Request $request, KnowledgePublication $publication, GroundedCompanionService $companion): RedirectResponse
    {
        $this->authorize('view', $publication);
        $data = $request->validate(['question' => ['required', 'string', 'min:2', 'max:2000']]);
        $session = $companion->ask($publication, $data['question'], $request->user());

        return redirect()->route('knowledge.companion.show', $session);
    }

    public function companion(AiGroundingSession $session): View
    {
        abort_unless(auth()->id() === $session->user_id || auth()->user()->isAdmin(), 403);
        $session->load(['sources', 'subject']);

        return view('knowledge.companion', compact('session'));
    }

    public function companionFeedback(Request $request, AiGroundingSession $session, GroundedQuestionIntelligenceService $questions): RedirectResponse
    {
        abort_unless(auth()->id() === $session->user_id || auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'rating' => ['required', 'in:helpful,not_helpful'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $metadata['feedback'] = [
            'rating' => $data['rating'],
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'recorded_at' => now()->toIso8601String(),
            'recorded_by' => $request->user()->id,
        ];
        $session->update(['metadata' => $metadata]);

        $subject = $session->subject;
        if ($subject instanceof KnowledgePublication) {
            $questions->clearPatternCache($subject);
        }

        return back()->with('success', 'Thanks. Your feedback will help AcadFlow improve future grounded retrieval patterns for this publication.');
    }

    public function commentPublication(Request $request, KnowledgePublication $publication, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$publication);$data=$request->validate(['body'=>['required','string','max:10000'],'parent_id'=>['nullable','integer','exists:engagement_comments,id']]);$engagement->comment($publication,$request->user(),$data['body'],['parent_id'=>$data['parent_id']??null,'visibility'=>$publication->visibility,'thread_title'=>$publication->title]);return back()->with('success','Comment added.');
    }

    public function commentCommunityPost(Request $request, KnowledgeCommunityPost $post, CommunityService $communities, EngagementService $engagement): RedirectResponse
    {
        $communities->ensureVisible($post->community,$request->user());$data=$request->validate(['body'=>['required','string','max:10000'],'parent_id'=>['nullable','integer','exists:engagement_comments,id']]);$engagement->comment($post,$request->user(),$data['body'],['parent_id'=>$data['parent_id']??null,'visibility'=>$post->community->visibility,'thread_title'=>$post->title]);return back()->with('success','Comment added.');
    }

    public function reactPublication(Request $request, KnowledgePublication $publication, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$publication);$data=$request->validate(['reaction'=>['required','in:like,helpful,insightful,celebrate']]);$engagement->react($publication,$request->user(),$data['reaction']);return back()->with('success','Reaction updated.');
    }

    public function reportPublication(Request $request, KnowledgePublication $publication, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$publication);$data=$request->validate(['reason'=>['required','in:spam,academic_integrity,harassment,misinformation,copyright,privacy,other'],'details'=>['nullable','string','max:5000']]);$engagement->report($publication,$request->user(),$data['reason'],$data['details']??null);return back()->with('success','Report submitted for human moderation.');
    }

    public function sharePublication(Request $request, KnowledgePublication $publication, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$publication);$data=$request->validate(['channel'=>['required','in:copy_link,email,whatsapp,linkedin,x,internal']]);$engagement->share($publication,$request->user(),$data['channel']);return back()->with('success','Share recorded.');
    }

    public function followPublication(Request $request, KnowledgePublication $publication, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$publication);$active=$engagement->subscribe($publication,$request->user());return back()->with('success',$active?'Publication followed.':'Publication unfollowed.');
    }
}
