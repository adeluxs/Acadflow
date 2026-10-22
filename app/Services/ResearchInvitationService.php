<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\ResearchProject;
use App\Models\ResearchProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchInvitationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function inviteSupervisor(ResearchProject $project, User $inviter, User $invitee, ?string $message = null): ResearchProjectInvitation
    {
        if (! $project->workspace_mode || $project->workspace_mode !== 'institutional') {
            throw ValidationException::withMessages(['supervisor_invitee_id' => 'Supervisor invitations are available only for institutional research workspaces.']);
        }

        $qualified=app(AcademicOrganisationService::class)->applyMembershipRole($invitee,(int)$project->university_id,null,'lecturer');
        if (! $invitee->is_active || !$qualified || ! $invitee->isLecturer()) {
            throw ValidationException::withMessages(['supervisor_invitee_id' => 'Choose an active lecturer to invite as supervisor.']);
        }

        if ($invitee->university_id !== $project->university_id) {
            throw ValidationException::withMessages(['supervisor_invitee_id' => 'The invited supervisor must belong to the same institution as this project.']);
        }

        if ($project->supervisor_id === $invitee->id) {
            throw ValidationException::withMessages(['supervisor_invitee_id' => 'This lecturer is already the project supervisor.']);
        }

        $invitation = $project->invitations()->updateOrCreate(
            ['invitee_id' => $invitee->id, 'role' => 'supervisor'],
            [
                'inviter_id' => $inviter->id,
                'status' => 'pending',
                'message' => $message,
                'expires_at' => now()->addDays(7),
                'responded_at' => null,
            ]
        );

        $this->notifications->send(
            $invitee,
            NotificationType::RESEARCH_INVITATION,
            'Research supervision invitation',
            $inviter->full_name.' invited you to supervise “'.$project->title.'”.',
            [
                'message' => $inviter->full_name.' invited you to supervise “'.$project->title.'”.',
                'research_uuid' => $project->uuid,
                'invitation_uuid' => $invitation->uuid,
                'role' => 'supervisor',
            ]
        );

        return $invitation;
    }

    public function respond(ResearchProjectInvitation $invitation, User $invitee, bool $accept): ResearchProjectInvitation
    {
        if ($invitation->invitee_id !== $invitee->id) {
            abort(403);
        }
        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages(['invitation' => 'This research invitation has already been answered.']);
        }
        if ($invitation->expires_at?->isPast()) {
            $invitation->update(['status' => 'expired']);
            throw ValidationException::withMessages(['invitation' => 'This research invitation has expired.']);
        }

        $invitation->loadMissing(['project', 'inviter']);

        DB::transaction(function () use ($invitation, $accept): void {
            $project = $invitation->project()->lockForUpdate()->firstOrFail();

            if ($accept && $invitation->role === 'supervisor') {
                if ($project->supervisor_id && $project->supervisor_id !== $invitation->invitee_id) {
                    throw ValidationException::withMessages(['invitation' => 'This project already has an accepted supervisor.']);
                }
                $project->update(['supervisor_id' => $invitation->invitee_id]);
                $project->invitations()
                    ->where('role', 'supervisor')
                    ->where('status', 'pending')
                    ->where('id', '!=', $invitation->id)
                    ->update(['status' => 'superseded', 'responded_at' => now()]);
            }

            $invitation->update([
                'status' => $accept ? 'accepted' : 'declined',
                'responded_at' => now(),
            ]);
        });

        $this->notifications->send(
            $invitation->inviter,
            NotificationType::RESEARCH_INVITATION_RESPONSE,
            'Research supervision invitation response',
            $invitee->full_name.($accept ? ' accepted ' : ' declined ').'the invitation for “'.$invitation->project->title.'”.',
            [
                'message' => $invitee->full_name.($accept ? ' accepted ' : ' declined ').'the research supervision invitation.',
                'research_uuid' => $invitation->project->uuid,
                'invitation_uuid' => $invitation->uuid,
                'decision' => $accept ? 'accepted' : 'declined',
            ]
        );

        return $invitation->fresh(['project', 'invitee', 'inviter']);
    }
}
