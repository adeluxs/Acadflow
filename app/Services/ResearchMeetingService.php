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
        $attendeeIds = collect($data['attendee_ids'] ?? [$project->owner_id, $project->supervisor_id, $project->co_supervisor_id])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $scheduledAt = Carbon::parse($data['scheduled_at']);

        $meeting = DB::transaction(function () use ($project, $actor, $data, $attendeeIds, $scheduledAt) {
            $meeting = $project->meetings()->create([
                'scheduled_by' => $actor->id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'location' => $data['location'] ?? null,
                'online_url' => $data['online_url'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'calendar_uid' => (string) Str::uuid(),
            ]);

            if ($attendeeIds->isNotEmpty()) {
                $now = now();
                DB::table('research_meeting_attendees')->insert(
                    $attendeeIds->map(fn (int $userId) => [
                        'research_meeting_id' => $meeting->id,
                        'user_id' => $userId,
                        'response' => $userId === $actor->id ? 'accepted' : 'pending',
                        'attended' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
                DB::table('research_meeting_reminders')->insert(
                    $attendeeIds->map(fn (int $userId) => [
                        'research_meeting_id' => $meeting->id,
                        'user_id' => $userId,
                        'remind_at' => $scheduledAt->copy()->subDay(),
                        'channel' => 'in_app',
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            foreach ($data['action_items'] ?? [] as $item) {
                if (blank($item['title'] ?? null)) continue;
                $meeting->actionItemRecords()->create(['assigned_to' => $item['assigned_to'] ?? null, 'title' => $item['title'], 'description' => $item['description'] ?? null, 'due_at' => $item['due_at'] ?? null]);
            }

            return $meeting;
        });

        // Notifications may send email/push traffic. Keep all external/network work
        // outside the database transaction so a slow provider cannot hold locks.
        User::query()->whereKey($attendeeIds)->get()->each(function (User $user) use ($meeting, $project, $scheduledAt): void {
            $this->notifications->send(
                $user,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                'Research meeting scheduled',
                $project->title.' meeting is scheduled for '.$scheduledAt->toDayDateTimeString(),
                ['research_project_uuid' => $project->uuid, 'meeting_uuid' => $meeting->uuid]
            );
        });

        return $meeting->fresh(['attendees.user', 'actionItemRecords.assignee', 'reminders']);
    }

    public function complete(ResearchMeeting $meeting, array $data): ResearchMeeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $meeting->update(['notes' => $data['notes'] ?? $meeting->notes, 'status' => 'completed', 'completed_at' => now()]);
            $attendance = collect($data['attendance'] ?? [])
                ->mapWithKeys(fn ($attended, $userId) => [(int) $userId => (bool) $attended]);
            foreach ([true, false] as $attended) {
                $userIds = $attendance->filter(fn (bool $value) => $value === $attended)->keys();
                if ($userIds->isNotEmpty()) {
                    $meeting->attendees()->whereIn('user_id', $userIds)->update(['attended' => $attended]);
                }
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
        return implode("\r\n", ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//AcadSyntra//Research Studio//EN','BEGIN:VEVENT','UID:'.$meeting->calendar_uid,'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),'DTSTART:'.$start,'DTEND:'.$end,'SUMMARY:'.addcslashes($meeting->project->title.' Research Meeting', ',;'),'DESCRIPTION:'.addcslashes((string) $meeting->agenda, "\n,;"),'LOCATION:'.addcslashes((string) ($meeting->online_url ?: $meeting->location), ',;'),'END:VEVENT','END:VCALENDAR','']);
    }
}
