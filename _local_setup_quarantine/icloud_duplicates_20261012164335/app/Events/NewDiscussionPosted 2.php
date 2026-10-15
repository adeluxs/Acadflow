<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDiscussionPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $discussion;

    public $course;

    public $recipients;

    public $isReply;

    public function __construct($discussion, $course, $recipients, bool $isReply = false)
    {
        $this->discussion = $discussion;
        $this->course = $course;
        $this->recipients = $recipients;
        $this->isReply = $isReply;
    }
}
