<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\ResearchMeeting;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ResearchMeetingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function schedule(ResearchProject $project, User $actor, array $data): ResearchMeeting
    {
        return DB::transaction(function () use ($project, $actor, $data) {
            $meeting = $project->meetings()->create([
                'scheduled_by' => $actor->id,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'location' => $data['location'] ?? null,
                'online_url' => $data['online_url'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'calendar_uid' => (string) Str::uuid(),
            ]);
            $attendees = collect($data['attendee_ids'] ?? [$project->owner_id, $project->supervisor_id, $project->co_supervisor_id])->filter()->unique();
            foreach ($attendees as $userId) {
                $meeting->attendees()->create(['user_id' => $userId, 'response' => $userId === $actor->id ? 'accepted' : 'pending']);
                $meeting->reminders()->create(['user_id' => $userId, 'remind_at' => Carbon::parse($data['scheduled_at'])->subDay(), 'channel' => 'in_app', 'status' => 'pending']);
                if ($user = User::find($userId)) {
                    $this->notifications->send($user, NotificationType::SYSTEM_ANNOUNCEMENT, 'Research meeting scheduled', $project->title.' meeting is scheduled for '.Carbon::parse($data['scheduled_at'])->toDayDateTimeString(), ['research_project_uuid' => $project->uuid, 'meeting_uuid' => $meeting->uuid]);
                }
            }
            foreach ($data['action_items'] ?? [] as $item) {
                if (blank($item['title'] ?? null)) continue;
                $meeting->actionItemRecords()->create(['assigned_to' => $item['assigned_to'] ?? null, 'title' => $item['title'], 'description' => $item['description'] ?? null, 'due_at' => $item['due_at'] ?? null]);
            }
            return $meeting->fresh(['attendees.user', 'actionItemRecords.assignee', 'reminders']);
        });
    }

    public function complete(ResearchMeeting $meeting, array $data): ResearchMeeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $meeting->update(['notes' => $data['notes'] ?? $meeting->notes, 'status' => 'completed', 'completed_at' => now()]);
            foreach ($data['attendance'] ?? [] as $userId => $attended) {
                $meeting->attendees()->where('user_id', $userId)->update(['attended' => (bool) $attended]);
            }
            foreach ($data['action_items'] ?? [] as $item) {
                if (blank($item['title'] ?? null)) continue;
                $meeting->actionItemRecords()->create(['assigned_to' => $item['assigned_to'] ?? null, 'title' => $item['title'], 'description' => $item['description'] ?? null, 'due_at' => $item['due_at'] ?? null]);
            }
            return $meeting->fresh(['attendees.user', 'actionItemRecords.assignee']);
        });
    }

    public function sendReminder(\App\Models\ResearchMeetingReminder $reminder): void
    {
        if ($reminder->sent_at || $reminder->status === 'cancelled') return;
        $reminder->loadMissing('meeting.project', 'user');
        if (! $reminder->meeting || ! $reminder->user || $reminder->meeting->status !== 'scheduled') {
            $reminder->update(['status' => 'cancelled']);
            return;
        }
        $this->notifications->send(
            $reminder->user,
            NotificationType::SYSTEM_ANNOUNCEMENT,
            'Research meeting reminder',
            $reminder->meeting->project->title.' meeting starts '.$reminder->meeting->scheduled_at->diffForHumans().'.',
            ['research_project_uuid' => $reminder->meeting->project->uuid, 'meeting_uuid' => $reminder->meeting->uuid]
        );
        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function ics(ResearchMeeting $meeting): string
    {
        $start = $meeting->scheduled_at->utc()->format('Ymd\THis\Z');
        $end = $meeting->scheduled_at->copy()->addMinutes($meeting->duration_minutes)->utc()->format('Ymd\THis\Z');
        return implode("\r\n", ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//AcadFlow//Research Studio//EN','BEGIN:VEVENT','UID:'.$meeting->calendar_uid,'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),'DTSTART:'.$start,'DTEND:'.$end,'SUMMARY:'.addcslashes($meeting->project->title.' Research Meeting', ',;'),'DESCRIPTION:'.addcslashes((string) $meeting->agenda, "\n,;"),'LOCATION:'.addcslashes((string) ($meeting->online_url ?: $meeting->location), ',;'),'END:VEVENT','END:VCALENDAR','']);
    }
}
