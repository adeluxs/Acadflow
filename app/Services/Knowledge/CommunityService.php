<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeCommunity;
use App\Models\KnowledgeCommunityInvitation;
use App\Models\KnowledgeCommunityPost;
use App\Models\KnowledgePollOption;
use App\Models\KnowledgePollVote;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\EngagementService;
use App\Services\SocialNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityService
{
    public function __construct(
        private readonly ContentWorkspaceService $workspace,
        private readonly EngagementService $engagement,
        private readonly SocialNotificationService $notifications,
    ) {}

    public function create(User $owner, array $data): KnowledgeCommunity
    {
        return DB::transaction(function () use ($owner, $data) {
            $community = KnowledgeCommunity::create([
                'university_id' => $data['university_id'] ?? $owner->university_id,
                'department_id' => $data['department_id'] ?? $owner->department_id,
                'category_id' => $data['category_id'] ?? null,
                'owner_id' => $owner->id,
                'cover_media_id' => $data['cover_media_id'] ?? null,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'description' => $data['description'] ?? null,
                'community_type' => $data['community_type'] ?? 'professional_interest',
                'visibility' => $data['visibility'] ?? 'public',
                'membership_mode' => $data['membership_mode'] ?? 'open',
                'requires_moderation' => (bool) ($data['requires_moderation'] ?? false),
                'status' => $data['status'] ?? 'active',
                'rules' => $data['rules'] ?? [],
                'settings' => $data['settings'] ?? [],
                'published_at' => ($data['status'] ?? 'active') === 'active' ? now() : null,
            ]);
            $community->members()->create(['user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            $this->syncTags($community, $data['tag_ids'] ?? []);
            $this->recount($community);
            return $community->fresh(['members.user','category','tags']);
        });
    }

    public function update(KnowledgeCommunity $community, User $actor, array $data): KnowledgeCommunity
    {
        $this->ensureCanManage($community, $actor);
        $community->fill(collect($data)->only([
            'name','description','community_type','visibility','membership_mode','requires_moderation',
            'category_id','cover_media_id','rules','settings','status',
        ])->all());
        if (array_key_exists('name', $data) && $community->isDirty('name')) {
            $community->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        }
        if (($data['status'] ?? null) === 'active' && ! $community->published_at) $community->published_at = now();
        $community->save();
        if (array_key_exists('tag_ids', $data)) $this->syncTags($community, $data['tag_ids']);
        return $community->fresh(['category','tags']);
    }

    public function archive(KnowledgeCommunity $community, User $actor): void
    {
        $this->ensureCanManage($community, $actor);
        $community->update(['status' => 'archived']);
    }

    public function join(KnowledgeCommunity $community, User $user): string
    {
        $this->ensureVisible($community, $user);
        if ($community->status !== 'active') throw ValidationException::withMessages(['community' => 'This community is not accepting members.']);
        if ($community->membership_mode === 'closed') throw ValidationException::withMessages(['community' => 'This community is invitation-only.']);
        $status = $community->membership_mode === 'approval' ? 'pending' : 'active';
        $membership = $community->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => 'member', 'status' => $status, 'joined_at' => $status === 'active' ? now() : null, 'left_at' => null]
        );
        $this->recount($community);
        if ($status === 'pending') {
            $this->notifications->send($community->owner, 'community_join_request', 'New community join request', $user->full_name.' requested to join '.$community->name.'.', ['community_uuid'=>$community->uuid,'member_id'=>$membership->id]);
        } else {
            $this->notifications->send($user, 'community_membership_approved', 'Community joined', 'You are now a member of '.$community->name.'.', ['community_uuid'=>$community->uuid]);
        }
        return $status;
    }

    public function leave(KnowledgeCommunity $community, User $user): void
    {
        abort_if($community->owner_id === $user->id, 422, 'Transfer ownership before leaving this community.');
        $membership = $community->members()->where('user_id',$user->id)->firstOrFail();
        $membership->update(['status'=>'removed','left_at'=>now()]);
        $this->recount($community);
    }

    public function invite(KnowledgeCommunity $community, User $actor, User $invitee, string $role = 'member'): KnowledgeCommunityInvitation
    {
        $this->ensureCanModerate($community, $actor);
        abort_if($invitee->id === $actor->id, 422, 'You are already a member.');
        if ($community->university_id && $community->visibility !== 'public') abort_unless($invitee->university_id === $community->university_id, 422, 'This user is outside the community institution.');

        return DB::transaction(function () use ($community,$actor,$invitee,$role) {
            $token = Str::random(64);
            $invitation = $community->invitations()->updateOrCreate(
                ['invitee_id'=>$invitee->id,'status'=>'pending'],
                ['inviter_id'=>$actor->id,'email'=>$invitee->email,'role'=>$role,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDays(14)]
            );
            $this->notifications->send($invitee, 'community_invitation', 'Community invitation', $actor->full_name.' invited you to join '.$community->name.'.', ['community_uuid'=>$community->uuid,'invitation_uuid'=>$invitation->uuid]);
            return $invitation;
        });
    }

    public function respondToInvitation(KnowledgeCommunityInvitation $invitation, User $user, bool $accept): void
    {
        abort_unless($invitation->invitee_id === $user->id && $invitation->status === 'pending', 403);
        abort_if($invitation->expires_at->isPast(), 422, 'This invitation has expired.');
        DB::transaction(function () use ($invitation,$user,$accept) {
            $invitation->update(['status'=>$accept?'accepted':'declined','responded_at'=>now()]);
            if ($accept) {
                $invitation->community->members()->updateOrCreate(['user_id'=>$user->id], ['role'=>$invitation->role,'status'=>'active','invited_by'=>$invitation->inviter_id,'joined_at'=>now(),'left_at'=>null]);
                $this->recount($invitation->community);
            }
            $this->notifications->send($invitation->inviter, 'community_invitation_response', 'Community invitation response', $user->full_name.($accept?' accepted ':' declined ').'the invitation to '.$invitation->community->name.'.', ['community_uuid'=>$invitation->community->uuid]);
        });
    }

    public function moderateMember(KnowledgeCommunity $community, User $actor, User $member, string $status, string $role = 'member'): void
    {
        $this->ensureCanModerate($community, $actor);
        abort_if($member->id === $community->owner_id && ($status !== 'active' || $role !== 'owner'), 422, 'The owner cannot be removed or demoted.');
        $membership = $community->members()->updateOrCreate(['user_id' => $member->id], [
            'status' => $status,
            'role' => $role,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'joined_at' => $status === 'active' ? now() : null,
            'left_at' => in_array($status,['removed','suspended'],true) ? now() : null,
        ]);
        $this->recount($community);
        $this->notifications->send($member, 'community_membership_updated', 'Community membership updated', 'Your membership in '.$community->name.' is now '.$status.'.', ['community_uuid'=>$community->uuid,'membership_id'=>$membership->id]);
    }

    public function post(KnowledgeCommunity $community, User $author, array $data): KnowledgeCommunityPost
    {
        $membership = $community->members()->where('user_id', $author->id)->where('status', 'active')->first();
        abort_unless($membership || $author->isAdmin(), 403, 'Active community membership is required.');
        return DB::transaction(function () use ($community, $author, $data, $membership) {
            $canModerate = $author->isAdmin() || in_array($membership?->role,['owner','administrator','moderator'],true);
            $status = $community->requires_moderation && ! $canModerate ? 'pending' : 'published';
            $document = $this->workspace->create([
                'document_type' => 'community_post', 'title' => $data['title'] ?? 'Community post', 'body' => $data['body'],
                'status' => $status, 'visibility' => $community->visibility === 'public' ? 'public' : 'institution',
                'metadata' => ['community_uuid' => $community->uuid],
            ], $author);
            $post = $community->posts()->create([
                'author_id' => $author->id, 'content_document_id' => $document->id, 'post_type' => $data['post_type'] ?? 'discussion',
                'title' => $data['title'] ?? null, 'status' => $status, 'metadata' => $data['metadata'] ?? [],
            ]);
            if ($post->post_type === 'poll') {
                $options = collect($data['poll_options'] ?? [])->map(fn ($option) => trim((string) $option))->filter()->unique()->values();
                if ($options->count() < 2) throw ValidationException::withMessages(['poll_options' => 'A poll requires at least two distinct options.']);
                foreach ($options as $position => $label) $post->pollOptions()->create(['label' => $label, 'position' => $position + 1]);
            }
            $this->engagement->threadFor($post, $community->university_id, $community->visibility === 'public' ? 'public' : 'institution', $post->title);
            if ($status === 'pending') $this->notifications->send($community->owner, 'community_post_pending', 'Community post awaiting review', $author->full_name.' submitted a post in '.$community->name.'.', ['community_uuid'=>$community->uuid,'post_uuid'=>$post->uuid]);
            return $post->fresh(['author','document','pollOptions.votes']);
        });
    }

    public function moderatePost(KnowledgeCommunityPost $post, User $actor, string $status, bool $pinned = false): void
    {
        $this->ensureCanModerate($post->community, $actor);
        $post->update(['status'=>$status,'is_pinned'=>$pinned]);
        $post->document?->update(['status'=>$status]);
        $this->notifications->send($post->author, 'community_post_reviewed', 'Community post reviewed', 'Your post in '.$post->community->name.' is now '.$status.'.', ['community_uuid'=>$post->community->uuid,'post_uuid'=>$post->uuid]);
    }

    public function votePoll(KnowledgePollOption $option, User $user): KnowledgePollOption
    {
        $post = $option->post()->with('community')->firstOrFail();
        $this->ensureVisible($post->community, $user);
        abort_unless($post->post_type === 'poll' && $post->status === 'published', 422, 'This poll is not available.');
        abort_unless($post->community->members()->where('user_id', $user->id)->where('status', 'active')->exists() || $user->isAdmin(), 403);

        return DB::transaction(function () use ($option, $post, $user) {
            KnowledgePollVote::query()->where('user_id', $user->id)->whereHas('option', fn ($query) => $query->where('knowledge_community_post_id', $post->id))->delete();
            KnowledgePollVote::create(['knowledge_poll_option_id' => $option->id, 'user_id' => $user->id, 'created_at' => now()]);
            return $option->fresh('votes');
        });
    }

    public function ensureVisible(KnowledgeCommunity $community, ?User $user): void
    {
        if ($community->visibility === 'public' && $community->status === 'active') return;
        abort_unless($user && ($user->isSuperAdmin() || $user->can('view',$community)), 403);
    }

    private function ensureCanManage(KnowledgeCommunity $community, User $actor): void { abort_unless($actor->can('update',$community),403); }
    private function ensureCanModerate(KnowledgeCommunity $community, User $actor): void { abort_unless($actor->can('moderate',$community),403); }
    private function recount(KnowledgeCommunity $community): void { $community->updateQuietly(['member_count'=>$community->members()->where('status','active')->count()]); }
    private function syncTags(KnowledgeCommunity $community, array $tagIds): void { if (method_exists($community,'tags')) $community->tags()->sync(array_values(array_unique(array_map('intval',$tagIds)))); }
}
