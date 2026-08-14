<?php

namespace App\Services\Knowledge;

use App\Ai\AiManager;
use App\Models\AcademicCertificate;
use App\Models\AcademicChallenge;
use App\Models\AcademicChallengeEntry;
use App\Models\AcademicEvent;
use App\Models\AcademicEventInvitation;
use App\Models\AcademicEventRegistration;
use App\Models\AcademicEventReminder;
use App\Models\KnowledgePublication;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\SocialNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventChallengeService
{
    public function __construct(
        private readonly ContentWorkspaceService $workspace,
        private readonly AiManager $ai,
        private readonly SocialNotificationService $notifications,
    ) {}

    public function createEvent(User $actor, array $data): AcademicEvent
    {
        return DB::transaction(function () use ($actor,$data) {
            $requestedStatus = $data['status'] ?? 'draft';
            $requiresModeration = (bool) ($data['requires_moderation'] ?? false);
            $status = $requestedStatus === 'published' && $requiresModeration && ! $actor->isAdmin() ? 'pending_review' : $requestedStatus;
            $event = AcademicEvent::create([
                'university_id'=>$data['university_id']??$actor->university_id,
                'faculty_id'=>$data['faculty_id']??$actor->faculty_id,
                'department_id'=>$data['department_id']??$actor->department_id,
                'organizer_id'=>$actor->id,
                'knowledge_community_id'=>$data['knowledge_community_id']??null,
                'group_id'=>$data['group_id']??null,
                'category_id'=>$data['category_id']??null,
                'cover_media_id'=>$data['cover_media_id']??null,
                'title'=>$data['title'],
                'slug'=>Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'description'=>$data['description']??null,
                'event_type'=>$data['event_type'],
                'format'=>$data['format']??'physical',
                'timezone'=>$data['timezone']??config('app.timezone','UTC'),
                'visibility'=>$data['visibility']??'public',
                'status'=>$status,
                'starts_at'=>$data['starts_at'],
                'ends_at'=>$data['ends_at']??null,
                'registration_deadline'=>$data['registration_deadline']??null,
                'registration_mode'=>$data['registration_mode']??'open',
                'location'=>$data['location']??null,
                'online_url'=>$data['online_url']??null,
                'capacity'=>$data['capacity']??null,
                'waitlist_enabled'=>(bool)($data['waitlist_enabled']??true),
                'requires_moderation'=>$requiresModeration,
                'certificate_enabled'=>(bool)($data['certificate_enabled']??false),
                'settings'=>$data['settings']??[],
                'published_at'=>$status==='published'?now():null,
            ]);
            $event->coOrganizers()->syncWithPivotValues($data['co_organizer_ids']??[], ['role'=>'co_organizer','added_by'=>$actor->id]);
            $event->tags()->sync($data['tag_ids']??[]);
            $this->syncEventReminders($event,$data['reminders']??[1440,60]);
            return $event->fresh(['organizer','coOrganizers','tags','reminders']);
        });
    }

    public function updateEvent(AcademicEvent $event, User $actor, array $data): AcademicEvent
    {
        abort_unless($actor->can('update',$event),403);
        return DB::transaction(function () use ($event,$actor,$data) {
            $event->fill(collect($data)->only([
                'title','description','event_type','format','timezone','visibility','starts_at','ends_at',
                'registration_deadline','registration_mode','location','online_url','capacity','waitlist_enabled',
                'certificate_enabled','category_id','cover_media_id','knowledge_community_id','group_id','faculty_id','department_id','settings',
            ])->all());
            if (array_key_exists('title',$data) && $event->isDirty('title')) $event->slug=Str::slug($data['title']).'-'.Str::lower(Str::random(6));
            $startsAtChanged=$event->isDirty('starts_at');
            $event->save();
            if ($startsAtChanged) $event->reminders()->update(['last_dispatched_at'=>null]);
            if (array_key_exists('co_organizer_ids',$data)) $event->coOrganizers()->syncWithPivotValues($data['co_organizer_ids'],['role'=>'co_organizer','added_by'=>$actor->id]);
            if (array_key_exists('tag_ids',$data)) $event->tags()->sync($data['tag_ids']);
            if (array_key_exists('reminders',$data)) $this->syncEventReminders($event,$data['reminders']);
            return $event->fresh(['coOrganizers','tags','reminders']);
        });
    }

    public function changeEventStatus(AcademicEvent $event, User $actor, string $status, ?string $reason = null): void
    {
        abort_unless($actor->can('update',$event),403);
        $allowed = match($status) {
            'published' => in_array($event->status,['draft','pending_review','cancelled'],true),
            'cancelled' => ! in_array($event->status,['completed','cancelled'],true),
            'completed' => in_array($event->status,['published','ongoing'],true),
            'draft' => in_array($event->status,['pending_review'],true),
            default => false,
        };
        abort_unless($allowed,422,'Invalid event status transition.');
        $event->update([
            'status'=>$status,
            'published_at'=>$status==='published'?now():$event->published_at,
            'cancelled_at'=>$status==='cancelled'?now():null,
            'cancellation_reason'=>$status==='cancelled'?$reason:null,
        ]);
        if ($status === 'cancelled') {
            $event->registrations()->whereIn('status',['pending','registered','waitlisted'])->update(['status'=>'cancelled','cancelled_at'=>now()]);
            $this->notifications->sendMany($event->registrations()->with('user')->get()->pluck('user'), 'event_cancelled', 'Event cancelled', $event->title.' has been cancelled.'.($reason?' Reason: '.$reason:''), ['event_uuid'=>$event->uuid]);
        }
    }

    public function deleteEvent(AcademicEvent $event, User $actor): void
    {
        abort_unless($actor->can('delete',$event),403);
        abort_if($event->registrations()->where('status','attended')->exists(),422,'An event with attendance records must be archived, not deleted.');
        $event->delete();
    }

    public function register(AcademicEvent $event, User $user): AcademicEventRegistration
    {
        abort_unless($user->can('register',$event),403);
        if ($event->status !== 'published' || $event->starts_at->isPast()) {
            throw ValidationException::withMessages(['event'=>'Registration is closed.']);
        }

        $deadline=$event->registration_deadline??$event->starts_at;
        if ($deadline->isPast()) {
            throw ValidationException::withMessages(['event'=>'The registration deadline has passed.']);
        }

        if ($event->registration_mode === 'invitation') {
            $invitation=$event->invitations()->where('invitee_id',$user->id)->where('status','accepted')->where('expires_at','>',now())->first();
            abort_unless($invitation || $event->coOrganizers()->whereKey($user->id)->exists(),403,'This event is invitation-only.');
        }

        return DB::transaction(function () use ($event,$user) {
            $registered=$event->registrations()->whereIn('status',['registered','attended'])->lockForUpdate()->count();
            $status=$event->registration_mode === 'approval' ? 'pending' : 'registered';
            if ($status === 'registered' && $event->capacity && $registered >= $event->capacity) {
                if (! $event->waitlist_enabled) {
                    throw ValidationException::withMessages(['event'=>'This event is full.']);
                }
                $status='waitlisted';
            }

            $registration=$event->registrations()->updateOrCreate(['user_id'=>$user->id],[
                'status'=>$status,
                'registered_at'=>now(),
                'cancelled_at'=>null,
                'check_in_code'=>hash('sha256',$event->uuid.'|'.$user->uuid.'|'.Str::random(16)),
            ]);

            if ($status === 'pending') {
                $this->notifications->sendMany(
                    collect([$event->organizer])->merge($event->coOrganizers),
                    'event_registration_pending',
                    'Event registration awaiting review',
                    $user->full_name.' requested to attend '.$event->title.'.',
                    ['event_uuid'=>$event->uuid,'registration_id'=>$registration->id]
                );
            } else {
                $this->notifications->send($user,'event_registration',$status==='registered'?'Event registration confirmed':'Added to event waitlist',($status==='registered'?'You are registered for ':'You are waitlisted for ').$event->title.'.',['event_uuid'=>$event->uuid,'registration_id'=>$registration->id]);
            }

            return $registration;
        });
    }

    public function reviewRegistration(AcademicEvent $event, AcademicEventRegistration $registration, User $actor, bool $approve): AcademicEventRegistration
    {
        abort_unless($actor->can('manageAttendees',$event),403);
        abort_unless($registration->academic_event_id === $event->id && $registration->status === 'pending',404);

        return DB::transaction(function () use ($event,$registration,$approve) {
            if (! $approve) {
                $registration->update(['status'=>'rejected','cancelled_at'=>now()]);
                $this->notifications->send($registration->user,'event_registration_rejected','Event registration not approved','Your registration request for '.$event->title.' was not approved.',['event_uuid'=>$event->uuid]);
                return $registration->fresh();
            }

            $registered=$event->registrations()->whereIn('status',['registered','attended'])->lockForUpdate()->count();
            $status='registered';
            if ($event->capacity && $registered >= $event->capacity) {
                if (! $event->waitlist_enabled) {
                    throw ValidationException::withMessages(['registration'=>'The event is full.']);
                }
                $status='waitlisted';
            }
            $registration->update(['status'=>$status,'cancelled_at'=>null]);
            $this->notifications->send($registration->user,'event_registration_reviewed',$status==='registered'?'Event registration approved':'Event waitlist placement','Your request for '.$event->title.' was '.($status==='registered'?'approved.':'approved and added to the waitlist.'),['event_uuid'=>$event->uuid]);
            return $registration->fresh();
        });
    }

    public function inviteEventAttendee(AcademicEvent $event, User $actor, User $invitee): AcademicEventInvitation
    {
        abort_unless($actor->can('manageAttendees',$event),403);
        abort_if($invitee->id === $event->organizer_id,422,'The organiser does not need an invitation.');
        if ($event->university_id && $event->visibility !== 'public') {
            abort_unless($invitee->university_id === $event->university_id,422,'The invitee is outside this event institution.');
        }

        $invitation=$event->invitations()->updateOrCreate(
            ['invitee_id'=>$invitee->id,'status'=>'pending'],
            ['inviter_id'=>$actor->id,'email'=>$invitee->email,'token_hash'=>hash('sha256',Str::random(64)),'expires_at'=>$event->starts_at->lt(now()->addDays(30)) ? $event->starts_at : now()->addDays(30)]
        );
        $this->notifications->send($invitee,'event_invitation','Academic event invitation',$actor->full_name.' invited you to '.$event->title.'.',['event_uuid'=>$event->uuid,'invitation_uuid'=>$invitation->uuid]);
        return $invitation;
    }

    public function respondEventInvitation(AcademicEventInvitation $invitation, User $user, bool $accept): ?AcademicEventRegistration
    {
        abort_unless($invitation->invitee_id === $user->id,403);
        abort_unless($invitation->status === 'pending' && $invitation->expires_at->isFuture(),422,'This invitation is no longer active.');

        $invitation->update(['status'=>$accept?'accepted':'declined','responded_at'=>now()]);
        if (! $accept) return null;
        return $this->register($invitation->event,$user);
    }

    public function dispatchReminder(AcademicEventReminder $reminder): int
    {
        $event=$reminder->event;
        if (! $reminder->is_active || ! $event || $event->status !== 'published' || $event->starts_at->isPast()) return 0;
        $dueAt=$event->starts_at->copy()->subMinutes($reminder->minutes_before);
        if ($dueAt->isFuture()) return 0;
        if ($reminder->last_dispatched_at && $reminder->last_dispatched_at->gte($event->starts_at->copy()->subMinutes($reminder->minutes_before))) return 0;
        $recipients=$event->registrations()->with('user')->whereIn('status',['registered','attended'])->get()->pluck('user')->filter();
        $this->notifications->sendMany($recipients,'event_reminder','Upcoming event reminder',$event->title.' starts '.$event->starts_at->diffForHumans().'.',['event_uuid'=>$event->uuid,'starts_at'=>$event->starts_at->toIso8601String()]);
        $reminder->update(['last_dispatched_at'=>now()]);
        return $recipients->count();
    }

    public function unregister(AcademicEvent $event, User $user): void
    {
        $registration=$event->registrations()->where('user_id',$user->id)->whereIn('status',['pending','registered','waitlisted'])->firstOrFail();
        $registration->update(['status'=>'cancelled','cancelled_at'=>now()]);
        if($registration->getOriginal('status')==='registered'&&$event->capacity) {
            $next=$event->registrations()->where('status','waitlisted')->oldest('registered_at')->first();
            if($next) {
                $next->update(['status'=>'registered']);
                $this->notifications->send($next->user,'event_waitlist_promoted','Event place available','A place is now available for '.$event->title.'.',['event_uuid'=>$event->uuid]);
            }
        }
    }

    public function markAttendance(AcademicEvent $event,User $user,User $actor):void
    {
        abort_unless($actor->can('manageAttendees',$event),403);
        $registration=$event->registrations()->firstOrCreate(['user_id'=>$user->id],['registered_at'=>now(),'status'=>'registered']);
        $registration->update(['status'=>'attended','attended_at'=>now(),'checked_in_by'=>$actor->id]);
        if($event->certificate_enabled)$this->certificate($user,$event,'Certificate of Attendance');
    }

    public function createChallenge(User $actor,array $data):AcademicChallenge
    {
        return DB::transaction(function()use($actor,$data){
            $requestedStatus=$data['status']??'draft';
            $requiresModeration=(bool)($data['requires_moderation']??false);
            $status=$requestedStatus==='published'&&$requiresModeration&&!$actor->isAdmin()?'pending_review':$requestedStatus;
            $challenge=AcademicChallenge::create([
                'university_id'=>$data['university_id']??$actor->university_id,'department_id'=>$data['department_id']??$actor->department_id,
                'organizer_id'=>$actor->id,'knowledge_community_id'=>$data['knowledge_community_id']??null,'group_id'=>$data['group_id']??null,
                'category_id'=>$data['category_id']??null,'cover_media_id'=>$data['cover_media_id']??null,
                'title'=>$data['title'],'slug'=>Str::slug($data['title']).'-'.Str::lower(Str::random(6)),'description'=>$data['description']??null,
                'challenge_type'=>$data['challenge_type'],'visibility'=>$data['visibility']??'public','participation_mode'=>$data['participation_mode']??'individual',
                'status'=>$status,'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'submission_deadline'=>$data['submission_deadline']??$data['ends_at'],
                'rules'=>$data['rules']??[],'eligibility_rules'=>$data['eligibility_rules']??[],'max_team_members'=>$data['max_team_members']??null,
                'judging_criteria'=>$data['judging_criteria']??[],'rewards'=>$data['rewards']??[],'public_voting_enabled'=>(bool)($data['public_voting_enabled']??false),
                'ai_assistance_enabled'=>(bool)($data['ai_assistance_enabled']??false),'requires_moderation'=>$requiresModeration,'published_at'=>$status==='published'?now():null,
            ]);
            $challenge->tags()->sync($data['tag_ids']??[]);
            $challenge->judges()->syncWithPivotValues($data['judge_ids']??[],['status'=>'active','invited_by'=>$actor->id,'accepted_at'=>now()]);
            return $challenge->fresh(['organizer','judges','tags']);
        });
    }

    public function updateChallenge(AcademicChallenge $challenge,User $actor,array $data):AcademicChallenge
    {
        abort_unless($actor->can('update',$challenge),403);
        return DB::transaction(function()use($challenge,$actor,$data){
            $challenge->fill(collect($data)->only([
                'title','description','challenge_type','visibility','participation_mode','starts_at','ends_at','submission_deadline',
                'rules','eligibility_rules','max_team_members','judging_criteria','rewards','public_voting_enabled','ai_assistance_enabled',
                'category_id','cover_media_id','knowledge_community_id','group_id','department_id',
            ])->all());
            if(array_key_exists('title',$data)&&$challenge->isDirty('title'))$challenge->slug=Str::slug($data['title']).'-'.Str::lower(Str::random(6));
            $challenge->save();
            if(array_key_exists('tag_ids',$data))$challenge->tags()->sync($data['tag_ids']);
            if(array_key_exists('judge_ids',$data))$challenge->judges()->syncWithPivotValues($data['judge_ids'],['status'=>'active','invited_by'=>$actor->id,'accepted_at'=>now()]);
            return $challenge->fresh(['judges','tags']);
        });
    }

    public function changeChallengeStatus(AcademicChallenge $challenge,User $actor,string $status):void
    {
        abort_unless($actor->can('update',$challenge),403);
        $transitions=['draft'=>['pending_review','published','cancelled'],'pending_review'=>['draft','published','cancelled'],'published'=>['active','judging','cancelled'],'active'=>['judging','cancelled'],'judging'=>['completed'],'completed'=>[],'cancelled'=>['draft']];
        abort_unless(in_array($status,$transitions[$challenge->status]??[],true),422,'Invalid challenge status transition.');
        $challenge->update(['status'=>$status,'published_at'=>$status==='published'?now():$challenge->published_at]);
    }

    public function deleteChallenge(AcademicChallenge $challenge,User $actor):void
    {
        abort_unless($actor->can('delete',$challenge),403);
        abort_if($challenge->entries()->where('is_final',true)->exists(),422,'A challenge with final entries must be archived, not deleted.');
        $challenge->delete();
    }

    public function submit(AcademicChallenge $challenge,User $user,array $data):AcademicChallengeEntry
    {
        abort_unless($user->can('submit',$challenge),403);
        $deadline=$challenge->submission_deadline??$challenge->ends_at;
        abort_unless(in_array($challenge->status,['published','active'],true)&&now()->between($challenge->starts_at,$deadline),422,'Challenge submissions are closed.');
        $this->assertEligibility($challenge,$user);

        $publication=null;
        if (! empty($data['knowledge_publication_id'])) {
            $publication=KnowledgePublication::findOrFail($data['knowledge_publication_id']);
            abort_unless($user->can('view',$publication),403,'You cannot submit this publication.');
        }

        $attachmentIds=collect($data['attachment_media_ids']??[])->map(fn($id)=>(int)$id)->unique()->values();
        if ($attachmentIds->isNotEmpty()) {
            $assets=MediaAsset::query()->whereIn('id',$attachmentIds)->get();
            abort_unless($assets->count()===$attachmentIds->count(),422,'One or more attachments are unavailable.');
            foreach ($assets as $asset) {
                abort_unless(in_array($asset->scan_status,['clean','skipped'],true),422,'Every attachment must pass security scanning.');
                $allowed=$asset->owner_id===$user->id || $asset->visibility==='public' || ($asset->visibility==='institution' && $asset->university_id && $asset->university_id===$user->university_id);
                abort_unless($allowed,403,'You cannot attach one or more selected files.');
            }
        }

        $memberIds=collect($data['team_member_ids']??[])->map(fn($id)=>(int)$id)->push($user->id)->unique()->values();
        if (in_array($challenge->participation_mode,['team','both'],true)) {
            abort_if($challenge->max_team_members&&$memberIds->count()>$challenge->max_team_members,422,'The team exceeds the maximum size.');
            $members=User::query()->whereIn('id',$memberIds)->where('is_active',true)->get()->keyBy('id');
            abort_unless($members->count()===$memberIds->count(),422,'Every teammate must be an active AcadFlow user.');
            foreach ($members as $member) {
                if ($challenge->university_id) abort_unless($member->university_id===$challenge->university_id,422,'All teammates must belong to the eligible institution.');
                $this->assertEligibility($challenge,$member);
                $alreadyCompeting=$challenge->entries()->where('user_id','!=',$user->id)->whereHas('teamMembers',fn($query)=>$query->where('users.id',$member->id)->where('academic_challenge_team_members.status','active'))->exists();
                abort_if($alreadyCompeting,422,$member->full_name.' is already part of another entry.');
            }
        } elseif ($memberIds->count()>1) {
            throw ValidationException::withMessages(['team_member_ids'=>'This challenge accepts individual entries only.']);
        }

        $existingEntry = $challenge->entries()->where('user_id', $user->id)->first();
        abort_if($existingEntry?->is_final, 422, 'A final challenge entry is locked. Contact an organiser if a correction is required.');

        $data['attachment_media_ids']=$attachmentIds->all();
        $data['team_member_ids']=$memberIds->reject(fn($id)=>$id===$user->id)->all();
        return DB::transaction(function()use($challenge,$user,$data,$publication,$memberIds){
            $document=null;
            if(!empty($data['body']))$document=$this->workspace->create([
                'document_type'=>'challenge_entry','title'=>$data['title'],'body'=>$data['body'],
                'status'=>($data['is_final']??false)?'submitted':'draft','visibility'=>'institution',
            ],$user);
            $isFinal=(bool)($data['is_final']??false);
            $entry=$challenge->entries()->updateOrCreate(['user_id'=>$user->id],[
                'team_name'=>in_array($challenge->participation_mode,['team','both'],true)?($data['team_name']??null):null,
                'content_document_id'=>$document?->id,'knowledge_publication_id'=>$publication?->id,
                'title'=>$data['title'],'submission_url'=>$data['submission_url']??null,'status'=>$isFinal?'submitted':'draft','is_final'=>$isFinal,
                'submitted_at'=>$isFinal?now():null,'metadata'=>['attachment_media_ids'=>$data['attachment_media_ids']??[]],
            ]);
            if(in_array($challenge->participation_mode,['team','both'],true)) {
                $sync=$memberIds->mapWithKeys(fn($id)=>[(int)$id=>['role'=>(int)$id===$user->id?'leader':'member','status'=>'active']])->all();
                $entry->teamMembers()->sync($sync);
            }
            if($isFinal&&$challenge->ai_assistance_enabled){
                $text=$document?->body??$entry->publication?->document?->body??'';
                $analysis=$this->ai->analyze('knowledge_moderation',['title'=>$entry->title,'text'=>$text,'criteria'=>$challenge->judging_criteria,'instruction'=>'Provide advisory criterion evidence only. Do not select a winner or finalize a score.'],$user,'challenge:'.$entry->uuid);
                $entry->update(['metadata'=>array_merge($entry->metadata??[],['ai_advisory'=>$analysis->toArray(),'human_judging_required'=>true])]);
            }
            if($isFinal)$this->notifications->send($challenge->organizer,'challenge_entry_submitted','Challenge entry submitted',$user->full_name.' submitted an entry to '.$challenge->title.'.',['challenge_uuid'=>$challenge->uuid,'entry_uuid'=>$entry->uuid]);
            return $entry->fresh(['document','publication','teamMembers']);
        });
    }

    public function judge(AcademicChallengeEntry $entry,User $judge,array $scores):AcademicChallengeEntry
    {
        abort_unless($judge->can('judge',$entry->challenge),403);
        abort_unless($entry->is_final,422,'Only final submissions can be judged.');
        abort_unless(in_array($entry->challenge->status, ['judging', 'active'], true), 422, 'Judging is not open for this challenge.');

        $criteria = collect($entry->challenge->judging_criteria ?? [])
            ->mapWithKeys(fn ($label) => [Str::slug((string) $label, '_') => (string) $label]);
        abort_unless($criteria->isNotEmpty(), 422, 'This challenge has no valid judging criteria.');

        foreach ($scores as $criterion => $value) {
            $criterionKey = Str::slug((string) $criterion, '_');
            abort_unless($criteria->has($criterionKey), 422, 'An unknown judging criterion was submitted.');
            $entry->scores()->updateOrCreate(
                ['judge_id' => $judge->id, 'criterion' => $criterionKey],
                [
                    'score' => (float) ($value['score'] ?? $value),
                    'feedback' => is_array($value) ? ($value['feedback'] ?? null) : null,
                    'is_ai_assisted' => false,
                    'metadata' => ['criterion_label' => $criteria->get($criterionKey)],
                ]
            );
        }
        $entry->update(['score'=>round((float)$entry->scores()->avg('score'),2),'status'=>'judged','reviewed_at'=>now()]);
        $this->notifications->send($entry->user,'challenge_entry_reviewed','Challenge entry reviewed','Your entry for '.$entry->challenge->title.' has been reviewed.',['challenge_uuid'=>$entry->challenge->uuid,'entry_uuid'=>$entry->uuid]);
        return $entry->fresh('scores.judge');
    }

    public function publishResults(AcademicChallenge $challenge,User $actor):void
    {
        abort_unless($actor->can('update',$challenge),403);
        abort_unless($challenge->status==='judging',422,'Results can only be published during judging.');
        DB::transaction(function()use($challenge){
            $rank=0;$last=null;$position=0;
            foreach($challenge->entries()->whereNotNull('score')->orderByDesc('score')->orderBy('submitted_at')->get() as $entry){
                $position++;if($last===null||(float)$entry->score<(float)$last)$rank=$position;$entry->update(['rank'=>$rank,'status'=>'ranked']);$last=$entry->score;
                $this->notifications->send($entry->user,'challenge_results','Challenge results published','Results for '.$challenge->title.' are available. Your rank: '.$rank.'.',['challenge_uuid'=>$challenge->uuid,'entry_uuid'=>$entry->uuid,'rank'=>$rank]);
            }
            $challenge->update(['status'=>'completed','results_published_at'=>now()]);
        });
    }

    public function vote(AcademicChallengeEntry $entry,User $user):bool
    {
        abort_unless($entry->challenge->public_voting_enabled&&in_array($entry->challenge->status,['published','active','judging'],true),403);
        abort_if($entry->user_id===$user->id||$entry->teamMembers()->whereKey($user->id)->exists(),422,'You cannot vote for your own entry.');
        return DB::transaction(function()use($entry,$user){
            $vote=$entry->votes()->where('user_id',$user->id)->first();
            if($vote){$vote->delete();$entry->whereKey($entry->id)->where('vote_count','>',0)->decrement('vote_count');return false;}
            $entry->votes()->create(['user_id'=>$user->id,'created_at'=>now()]);$entry->increment('vote_count');return true;
        });
    }

    public function advanceEventLifecycles(): array
    {
        $ongoing = AcademicEvent::query()
            ->where('status', 'published')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->update(['status' => 'ongoing']);

        $completed = AcademicEvent::query()
            ->whereIn('status', ['published', 'ongoing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'completed']);

        return compact('ongoing', 'completed');
    }

    public function advanceChallengeLifecycles(): array
    {
        $active = AcademicChallenge::query()
            ->where('status', 'published')
            ->where('starts_at', '<=', now())
            ->where('submission_deadline', '>=', now())
            ->update(['status' => 'active']);

        $judging = AcademicChallenge::query()
            ->whereIn('status', ['published', 'active'])
            ->where('submission_deadline', '<', now())
            ->update(['status' => 'judging']);

        return compact('active', 'judging');
    }

    private function syncEventReminders(AcademicEvent $event,array $minutes):void
    {
        $minutes=collect($minutes)->map(fn($value)=>(int)$value)->filter(fn($value)=>$value>=0&&$value<=10080)->unique()->values();
        $event->reminders()->whereNotIn('minutes_before',$minutes)->delete();
        foreach($minutes as $minute)$event->reminders()->updateOrCreate(['minutes_before'=>$minute,'channel'=>'database'],['is_active'=>true]);
    }

    private function assertEligibility(AcademicChallenge $challenge,User $user):void
    {
        $rules=$challenge->eligibility_rules??[];
        if(!empty($rules['account_types']))abort_unless(in_array($user->account_type,$rules['account_types'],true),403,'Your account type is not eligible.');
        if(!empty($rules['university_ids']))abort_unless(in_array($user->university_id,$rules['university_ids']),403,'Your institution is not eligible.');
        if(!empty($rules['department_ids']))abort_unless(in_array($user->department_id,$rules['department_ids']),403,'Your department is not eligible.');
        if(!empty($rules['academic_levels']))abort_unless(in_array($user->academic_level,$rules['academic_levels'],true),403,'Your academic level is not eligible.');
    }

    private function certificate(User $user,$subject,string $title):AcademicCertificate
    {
        $certificate=AcademicCertificate::firstOrCreate(['user_id'=>$user->id,'certifiable_type'=>$subject->getMorphClass(),'certifiable_id'=>$subject->getKey()],['title'=>$title.': '.$subject->title,'issuer'=>config('app.name'),'issued_on'=>today()]);
        if(!$certificate->file_path){$path='certificates/'.$certificate->uuid.'.html';Storage::disk('local')->put($path,'<!doctype html><html><body style="font-family:serif;text-align:center;padding:80px"><h1>'.e($title).'</h1><p>This certifies that</p><h2>'.e($user->full_name).'</h2><p>completed '.e($subject->title).'</p><p>Verification: '.e($certificate->verification_code).'</p></body></html>');$certificate->update(['file_path'=>$path]);}
        return $certificate;
    }
}
