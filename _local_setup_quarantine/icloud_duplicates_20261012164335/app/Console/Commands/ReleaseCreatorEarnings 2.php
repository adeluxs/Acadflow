<?php

namespace App\Console\Commands;

use App\Services\Commerce\CommerceService;
use Illuminate\Console\Command;

class ReleaseCreatorEarnings extends Command
{
    protected $signature = 'acadflow:release-creator-earnings {--limit=500 : Maximum pending allocations to release in one run}';
    protected $description = 'Release matured creator earnings from pending to available balance';

    public function handle(CommerceService $commerce): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $released = $commerce->releaseMatureCreatorEarnings($limit);
        $this->info("Released {$released} matured creator earning allocation(s).");

        return self::SUCCESS;
    }
}
