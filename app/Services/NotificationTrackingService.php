<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationTrackingService
{
    public function record(string $kind, ?User $user = null, ?Notification $notification = null, ?NotificationLog $delivery = null, array $metadata = [], ?string $key = null): NotificationEvent
    {
        return NotificationEvent::firstOrCreate(['event_key' => hash('sha256', $key ?? (string) Str::uuid())], [
            'notification_id' => $notification?->id, 'delivery_id' => $delivery?->id, 'user_id' => $user?->id ?? $delivery?->user_id,
            'university_id' => $notification?->university_id ?? $user?->university_id,
            'department_id' => $notification?->department_id ?? $user?->department_id,
            'role' => $user?->role, 'account_type' => $user?->account_type,
            'kind' => $kind, 'channel' => $delivery?->channel, 'notification_type' => $notification?->type,
            'category' => $notification?->category,
            // Analytics deliberately exclude message bodies, addresses, tokens,
            // endpoints, encryption keys and academic record contents.
            'metadata' => array_intersect_key($metadata, array_flip(['stage', 'campaign', 'reason', 'http_status', 'browser', 'device', 'supported', 'attempt', 'days_inactive', 'account_type'])),
            'created_at' => now(),
        ]);
    }
}
