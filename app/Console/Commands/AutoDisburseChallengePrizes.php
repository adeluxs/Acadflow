<?php

namespace App\Console\Commands;

use App\Services\Knowledge\ChallengePrizeService;
use Illuminate\Console\Command;

class AutoDisburseChallengePrizes extends Command
{
    protected $signature = 'challenges:auto-disburse-prizes';

    protected $description = 'Pay community challenge winners automatically when the creator has not done so within the configured days';

    public function handle(ChallengePrizeService $prizes): int
    {
        $this->info('Prize funds settled automatically: '.$prizes->autoDisburse());

        return self::SUCCESS;
    }
}
