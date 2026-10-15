<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupJoinRequest;
use App\Models\GroupResource;
use App\Models\GroupTask;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CollaborationGroupService
{
    public function __construct(private readonly SocialNotificationService $notifications) {}

    public function create(User $actor, array $data): Group
    {
        return DB::transaction(function () use ($actor,$data) {
            $course = ! empty($data['course_id']) ? Course::with('department.faculty')->findOrFail($data['course_id']) : null;
            if ($course) {
                abort_unless($actor->canAccessCourse($course),403);
                if ($actor->isStudent()) {
                    $enrolled = Enrollment::query()->where('user_id',$actor->id)->where('course_id',$course->id)->where('status','enrolled')->exists();
                    abort_unless($enrolled,422,'You must be enrolled in this course.');
                }
            }
            $group = Group::create([
                'university_id'=>$data['university_id']??$course?->department?->faculty?->university_id??$actor->university_id,
                'department_id'=>$data['department_id']??$course?->department_id??$actor->department_id,
                'knowledge_community_id'=>$data['knowledge_community_id']??null,
                'research_project_id'=>$data['research_project_id']??null,
                'course_id'=>$course?->id,
                'semester_id'=>$data['semester_id']??null,
                'name'=>$data['name'],'description'=>$data['description']??null,
                'group_type'=>$data['group_type']??'study','visibility'=>$data['visibility']??'private','membership_mode'=>$data['membership_mode']??'approval',
                'leader_id'=>$actor->id,'cover_media_id'=>$data['cover_media_id']??null,
                'status'=>'forming','is_locked'=>false,'max_members'=>$data['max_members']??10,'formed_at'=>now(),'settings'=>$data['settings']??[],
            ]);
            $group->members()->create(['user_id'=>$actor->id,'role'=>'leader','status'=>'active','joined_at'=>now()]);
            return $group->fresh(['leader','members.user','course','community','researchProject']);
        });
    }

    public function update(Group $group, User $actor, array $data): Group
    {
        abort_unless($actor->can('update',$group),403);
        $group->update(collect($data)->only(['name','description','group_type','visibility','membership_mode','max_members','status','is_locked','cover_media_id','settings'])->all());
        return $group->fresh();
    }

    public function join(Group $group, User $user, ?string $message = null): string
    {
        abort_unless($user->can('join',$group),403);
        abort_if($group->is_locked || $group->status === 'archived',422,'This group is not accepting members.');
        abort_if($group->members()->where('status','active')->count() >= $group->max_members,422,'This group is full.');
        if ($group->course_id) {
            abort_unless(Enrollment::query()->where('user_id',$user->id)->where('course_id',$group->course_id)->where('status','enrolled')->exists(),403,'You are not enrolled in the related course.');
        }
        if ($group->membership_mode === 'invitation') throw ValidationException::withMessages(['group'=>'This group is invitation-only.']);
        if ($group->membership_mode === 'open') {
            $group->members()->updateOrCreate(['user_id'=>$user->id],['role'=>'member','status'=>'active','joined_at'=>now(),'left_at'=>null]);
            $this->notifications->send($user,'group_joined','Group joined','You joined '.$group->name.'.',['group_uuid'=>$group->uuid]);
            return 'active';
        }
        $request=$group->joinRequests()->updateOrCreate(['user_id'=>$user->id],['message'=>$message,'status'=>'pending','reviewed_by'=>null,'reviewed_at'=>null]);
        $this->notifications->send($group->leader,'group_join_request','New group join request',$user->full_name.' requested to join '.$group->name.'.',['group_uuid'=>$group->uuid,'request_uuid'=>$request->uuid]);
        return 'pending';
    }

    public function reviewJoinRequest(GroupJoinRequest $joinRequest, User $actor, bool $approve): void
    {
        $group=$joinRequest->group;
        abort_unless($actor->can('manageMembers',$group),403);
        abort_unless($joinRequest->status==='pending',422,'This request was already reviewed.');
        if($approve) abort_if($group->members()->where('status','active')->count()>=$group->max_members,422,'The group is full.');
        DB::transaction(function()use($joinRequest,$actor,$approve,$group){
            $joinRequest->update(['status'=>$approve?'approved':'rejected','reviewed_by'=>$actor->id,'reviewed_at'=>now()]);
            if($approve)$group->members()->updateOrCreate(['user_id'=>$joinRequest->user_id],['role'=>'member','status'=>'active','joined_at'=>now(),'left_at'=>null]);
            $this->notifications->send($joinRequest->user,'group_join_reviewed','Group request reviewed','Your request to join '.$group->name.' was '.($approve?'approved.':'declined.'),['group_uuid'=>$group->uuid]);
        });
    }

    public function invite(Group $group, User $actor, User $invitee, string $role='member'): GroupInvitation
    {
        abort_unless($actor->can('manageMembers',$group),403);
        abort_if($group->members()->where('user_id',$invitee->id)->where('status','active')->exists(),422,'This user is already a member.');
        if($group->university_id&&$group->visibility!=='public')abort_unless($group->university_id===$invitee->university_id,422,'This user is outside the group institution.');
        $token=Str::random(64);
        $invitation=$group->invitations()->updateOrCreate(['invitee_id'=>$invitee->id,'status'=>'pending'],[
            'inviter_id'=>$actor->id,'email'=>$invitee->email,'role'=>$role,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDays(14),
        ]);
        $this->notifications->send($invitee,'group_invitation','Group invitation',$actor->full_name.' invited you to '.$group->name.'.',['group_uuid'=>$group->uuid,'invitation_uuid'=>$invitation->uuid]);
        return $invitation;
    }

    public function respondInvitation(GroupInvitation $invitation, User $user, bool $accept): void
    {
        abort_unless($invitation->invitee_id===$user->id&&$invitation->status==='pending',403);
        abort_if($invitation->expires_at->isPast(),422,'This invitation has expired.');
        DB::transaction(function()use($invitation,$user,$accept){
            $invitation->update(['status'=>$accept?'accepted':'declined','responded_at'=>now()]);
            if($accept){
                abort_if($invitation->group->members()->where('status','active')->count()>=$invitation->group->max_members,422,'The group is full.');
                $invitation->group->members()->updateOrCreate(['user_id'=>$user->id],['role'=>$invitation->role,'status'=>'active','invited_by'=>$invitation->inviter_id,'joined_at'=>now(),'left_at'=>null]);
            }
            $this->notifications->send($invitation->inviter,'group_invitation_response','Group invitation response',$user->full_name.($accept?' accepted ':' declined ').'the invitation to '.$invitation->group->name.'.',['group_uuid'=>$invitation->group->uuid]);
        });
    }

    public function leave(Group $group, User $user): void
    {
        abort_if($group->leader_id===$user->id,422,'Transfer leadership before leaving.');
        $group->members()->where('user_id',$user->id)->update(['status'=>'left','left_at'=>now()]);
    }

    public function removeMember(Group $group, User $actor, User $member): void
    {
        abort_unless($actor->can('manageMembers',$group),403);
        abort_if($member->id===$group->leader_id,422,'Transfer leadership before removing the leader.');
        $group->members()->where('user_id',$member->id)->update(['status'=>'removed','left_at'=>now()]);
        $this->notifications->send($member,'group_membership_removed','Removed from group','You were removed from '.$group->name.'.',['group_uuid'=>$group->uuid]);
    }

    public function transferLeadership(Group $group, User $actor, User $newLeader): void
    {
        abort_unless($actor->can('manageMembers',$group),403);
        abort_unless($group->members()->where('user_id',$newLeader->id)->where('status','active')->exists(),422,'The selected user is not an active member.');
        DB::transaction(function()use($group,$actor,$newLeader){
            $old=$group->leader_id;
            $group->update(['leader_id'=>$newLeader->id]);
            $group->members()->where('user_id',$old)->update(['role'=>'member']);
            $group->members()->where('user_id',$newLeader->id)->update(['role'=>'leader']);
            $this->notifications->send($newLeader,'group_leadership','Group leadership transferred','You are now the leader of '.$group->name.'.',['group_uuid'=>$group->uuid]);
        });
    }

    public function createTask(Group $group, User $actor, array $data): GroupTask
    {
        abort_unless($actor->can('update',$group),403);
        if(!empty($data['assignee_id']))abort_unless($group->members()->where('user_id',$data['assignee_id'])->where('status','active')->exists(),422,'Assignee must be an active group member.');
        $task=$group->tasks()->create($data+['creator_id'=>$actor->id]);
        if($task->assignee)$this->notifications->send($task->assignee,'group_task_assigned','New group task','You were assigned “'.$task->title.'” in '.$group->name.'.',['group_uuid'=>$group->uuid,'task_uuid'=>$task->uuid]);
        return $task;
    }

    public function updateTask(GroupTask $task, User $actor, array $data): GroupTask
    {
        abort_unless($actor->can('update',$task->group)||$task->assignee_id===$actor->id,403);
        if(isset($data['assignee_id']))abort_unless($task->group->members()->where('user_id',$data['assignee_id'])->where('status','active')->exists(),422,'Assignee must be an active member.');
        if(($data['status']??null)==='completed')$data['completed_at']=now();
        elseif(isset($data['status'])&&$data['status']!=='completed')$data['completed_at']=null;
        $task->update($data);
        return $task->fresh();
    }

    public function addResource(Group $group, User $actor, array $data): GroupResource
    {
        abort_unless($actor->can('view',$group),403);
        abort_unless($group->members()->where('user_id',$actor->id)->where('status','active')->exists()||$actor->isAdmin(),403);
        abort_unless(!empty($data['media_asset_id'])||!empty($data['external_url']),422,'Attach a secure file or an external URL.');
        if(!empty($data['media_asset_id'])){
            $asset=MediaAsset::findOrFail($data['media_asset_id']);
            abort_unless($asset->owner_id===$actor->id||$actor->isAdmin(),403);
            abort_unless(in_array($asset->scan_status,['clean','skipped'],true),422,'The file must pass security scanning before sharing.');
        }
        return $group->resources()->create($data+['uploaded_by'=>$actor->id]);
    }

    public function delete(Group $group, User $actor): void
    {
        abort_unless($actor->can('delete',$group),403);
        if($group->submissions()->exists()||$group->research_project_id){$group->update(['status'=>'archived','is_locked'=>true]);return;}
        $group->delete();
    }
}
