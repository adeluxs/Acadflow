<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendanceSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QrRefreshed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AttendanceSession $session,
        public string $qrCodeUrl,
        public string $expiresAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('attendance.session.' . $this->session->id),
            new PrivateChannel('attendance.course.' . $this->session->course_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'qr.refreshed';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'qr_code_url' => $this->qrCodeUrl,
            'expires_at' => $this->expiresAt,
            'course_id' => $this->session->course_id,
        ];
    }
}
