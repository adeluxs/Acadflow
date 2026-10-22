<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public function __construct(string $token) { parent::__construct($token); $this->onConnection(config('notification_delivery.connection', 'database'))->onQueue('default')->afterCommit(); }
}
