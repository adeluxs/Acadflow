<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public function __construct() { $this->onConnection(config('notification_delivery.connection', 'database'))->onQueue('default')->afterCommit(); }
}
