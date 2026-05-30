<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemAnnouncementBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $title;

    public $message;

    public $recipients;

    public $sender;

    public function __construct(string $title, string $message, $recipients, $sender = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->recipients = $recipients;
        $this->sender = $sender;
    }
}
