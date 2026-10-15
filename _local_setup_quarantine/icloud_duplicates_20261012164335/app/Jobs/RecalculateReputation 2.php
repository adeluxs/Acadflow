<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Reputation\ReputationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateReputation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $userId) {}

    public function handle(ReputationService $service): void
    {
        $user = User::find($this->userId);
        if ($user) {
            $service->recalculate($user);
        }
    }
}
