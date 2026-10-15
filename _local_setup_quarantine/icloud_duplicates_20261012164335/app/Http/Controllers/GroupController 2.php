<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\EngagementComment;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupJoinRequest;
use App\Models\GroupResource;
use App\Models\GroupTask;
use App\Models\KnowledgeCommunity;
use App\Models\ResearchProject;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\CollaborationGroupService;
use App\Services\EngagementService;
use App\Services\Media\MediaSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $user=$request->user();
        $filters=$request->validate(['q'=>['nullable','string','max:255'],'type'=>['nullable','string','max:40'],'visibility'=>['nullable','in:public,institution,private']]);
        $groups=Group::query()->with(['leader','course','community'])->withCount(['members as active_members_count'=>fn($q)=>$q->where('status','active')])
            ->where('status','!=','archived')
            ->where(function($q)use($user){$q->where('leader_id',$user->id)->orWhereHas('members',fn($m)=>$m->where('user_id',$user->id)->where('status','active'))->orWhere('visibility','public');if($user->university_id)$q->orWhere(fn($i)=>$i->where('visibility','institution')->where('university_id',$user->university_id));})
            ->when($filters['q']??null,fn($q,$term)=>$q->where(fn($s)=>$s->where('name','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')))
            ->when($filters['type']??null,fn($q,$type)=>$q->where('group_type',$type))
            ->when($filters['visibility']??null,fn($q,$visibility)=>$q->where('visibility',$visibility))
            ->latest()->paginate(18)->withQueryString();
        return view('groups.index',compact('groups','filters'));
    }

    public function create(Request $request, AcademicContextService $academicContext): View
    {
        $this->authorize('create',Group::class);
        $user=$request->user();
        $courses=Course::query()->with('department.faculty')->whereHas('department.faculty',fn($q)=>$user->university_id?$q->where('university_id',$user->university_id):$q->whereRaw('1=0'))->orderBy('code')->get();
        $communities=KnowledgeCommunity::query()->whereHas('members',fn($q)=>$q->where('user_id',$user->id)->where('status','active'))->orderBy('name')->get();
        $projects=ResearchProject::query()->where(fn($q)=>$q->where('owner_id',$user->id)->orWhereHas('members',fn($m)=>$m->where('user_id',$user->id)))->orderBy('title')->get();
        $semester=$academicContext->activeSemesterForUser($user);
        return view('groups.create',compact('courses','communities','projects','semester'));
    }

    public function store(Request $request, CollaborationGroupService $service, AcademicContextService $academicContext, MediaSecurityService $media): RedirectResponse
    {
        $this->authorize('create',Group::class);
        $data=$request->validate([
            'name'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'group_type'=>['required','in:study,research,project,departmental,professional,course,siwes,seminar'],
            'visibility'=>['required','in:public,institution,private'],'membership_mode'=>['required','in:open,approval,invitation'],'max_members'=>['required','integer','min:2','max:250'],
            'course_id'=>['nullable','integer','exists:courses,id'],'knowledge_community_id'=>['nullable','integer','exists:knowledge_communities,id'],'research_project_id'=>['nullable','integer','exists:research_projects,id'],
            'cover_media_id'=>['nullable','integer','exists:media_assets,id'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192'],
        ]);
        $data['semester_id']=! empty($data['course_id'] ?? null) ? $academicContext->activeSemesterForUser($request->user())?->id : null;
        unset($data['cover_image']);
        $group=$service->create($request->user(),$data);
        if($request->hasFile('cover_image')){$asset=$media->store($request->file('cover_image'),$request->user(),$group,$group->visibility==='public'?'public':($group->visibility==='institution'?'institution':'private'),['purpose'=>'group_cover']);$group->update(['cover_media_id'=>$asset->id]);}
        return redirect()->route('groups.show',$group)->with('success','Collaboration group created.');
    }

    public function show(Request $request, Group $group, EngagementService $engagement): View
    {
        $this->authorize('view',$group);
        $group->load(['leader','coverMedia','course','semester','community','researchProject','members'=>fn($q)=>$q->with('user')->where('status','active'),'joinRequests'=>fn($q)=>$q->with('user')->where('status','pending'),'invitations.invitee','tasks.assignee','resources.uploader','resources.media','events','challenges']);
        $comments=$engagement->commentsFor($group,20);
        return view('groups.show',compact('group','comments'));
    }

    public function edit(Group $group): View
    {
        $this->authorize('update',$group);
        return view('groups.edit',compact('group'));
    }

    public function update(Request $request, Group $group, CollaborationGroupService $service, MediaSecurityService $media): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'group_type'=>['required','in:study,research,project,departmental,professional,course,siwes,seminar'],'visibility'=>['required','in:public,institution,private'],'membership_mode'=>['required','in:open,approval,invitation'],'max_members'=>['required','integer','min:2','max:250'],'status'=>['required','in:forming,complete,archived'],'is_locked'=>['nullable','boolean'],'cover_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:8192']]);
        unset($data['cover_image']);
        if($request->hasFile('cover_image')){$visibility=$data['visibility']??$group->visibility;$asset=$media->store($request->file('cover_image'),$request->user(),$group,$visibility==='public'?'public':($visibility==='institution'?'institution':'private'),['purpose'=>'group_cover']);$data['cover_media_id']=$asset->id;}
        $service->update($group,$request->user(),$data);
        return redirect()->route('groups.show',$group)->with('success','Group settings updated.');
    }

    public function join(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['message'=>['nullable','string','max:1000']]);
        $status=$service->join($group,$request->user(),$data['message']??null);
        return back()->with('success',$status==='active'?'You joined the group.':'Your join request was submitted.');
    }

    public function reviewJoinRequest(Request $request, GroupJoinRequest $joinRequest, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject']]);
        $service->reviewJoinRequest($joinRequest,$request->user(),$data['decision']==='approve');
        return back()->with('success','Join request reviewed.');
    }

    public function invite(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['user_id'=>['required','integer','exists:users,id'],'role'=>['required','in:member,administrator']]);
        $service->invite($group,$request->user(),User::findOrFail($data['user_id']),$data['role']);
        return back()->with('success','Group invitation sent.');
    }

    public function respondInvitation(Request $request, GroupInvitation $invitation, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:accept,decline']]);
        $service->respondInvitation($invitation,$request->user(),$data['decision']==='accept');
        return redirect()->route('groups.show',$invitation->group)->with('success','Invitation response recorded.');
    }

    public function leave(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $service->leave($group,$request->user());
        return redirect()->route('groups.index')->with('success','You left the group.');
    }

    public function removeMember(Request $request, Group $group, User $member, CollaborationGroupService $service): RedirectResponse
    {
        $service->removeMember($group,$request->user(),$member);
        return back()->with('success','Member removed.');
    }

    public function transferLeadership(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['new_leader_id'=>['required','integer','exists:users,id']]);
        $service->transferLeadership($group,$request->user(),User::findOrFail($data['new_leader_id']));
        return back()->with('success','Leadership transferred.');
    }

    public function storeTask(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['title'=>['required','string','max:255'],'description'=>['nullable','string','max:3000'],'assignee_id'=>['nullable','integer','exists:users,id'],'priority'=>['required','in:low,normal,high,urgent'],'due_at'=>['nullable','date']]);
        $service->createTask($group,$request->user(),$data);
        return back()->with('success','Group task created.');
    }

    public function updateTask(Request $request, GroupTask $task, CollaborationGroupService $service): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:open,in_progress,blocked,completed,cancelled'],'assignee_id'=>['nullable','integer','exists:users,id'],'priority'=>['nullable','in:low,normal,high,urgent'],'due_at'=>['nullable','date']]);
        $service->updateTask($task,$request->user(),$data);
        return back()->with('success','Task updated.');
    }

    public function storeResource(Request $request, Group $group, CollaborationGroupService $service, MediaSecurityService $media): RedirectResponse
    {
        $data=$request->validate([
            'title'=>['required','string','max:255'],
            'description'=>['nullable','string','max:2000'],
            'media_asset_id'=>['nullable','integer','exists:media_assets,id','required_without_all:file,external_url'],
            'file'=>['nullable','file','max:51200','mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,jpg,jpeg,png,webp,zip','required_without_all:media_asset_id,external_url'],
            'external_url'=>['nullable','url','max:2000','required_without_all:media_asset_id,file'],
            'visibility'=>['required','in:members,public'],
        ]);
        if($request->hasFile('file')){$asset=$media->store($request->file('file'),$request->user(),$group,$data['visibility']==='public'&&$group->visibility==='public'?'public':($group->university_id?'institution':'private'),['purpose'=>'group_resource']);$data['media_asset_id']=$asset->id;}
        unset($data['file']);
        $service->addResource($group,$request->user(),$data);
        return back()->with('success','Resource shared.');
    }

    public function comment(Request $request, Group $group, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view',$group);
        abort_unless($group->members()->where('user_id',$request->user()->id)->where('status','active')->exists()||$request->user()->isAdmin(),403);
        $data=$request->validate(['body'=>['required','string','max:10000'],'parent_id'=>['nullable','integer','exists:engagement_comments,id']]);
        $engagement->comment($group,$request->user(),$data['body'],['parent_id'=>$data['parent_id']??null,'university_id'=>$group->university_id,'visibility'=>$group->visibility,'thread_title'=>$group->name]);
        return back()->with('success','Discussion message posted.');
    }

    public function report(Request $request, Group $group, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view', $group);
        $data = $request->validate([
            'reason' => ['required', 'in:spam,harassment,misinformation,privacy,policy,other'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);
        $engagement->report($group, $request->user(), $data['reason'], $data['details'] ?? null);

        return back()->with('success', 'Group report submitted for human moderation.');
    }

    public function destroy(Request $request, Group $group, CollaborationGroupService $service): RedirectResponse
    {
        $service->delete($group,$request->user());
        return redirect()->route('groups.index')->with('success','Group archived or deleted safely.');
    }
}
