<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneResearchExports extends Command
{
    protected $signature = 'research:prune-exports';
    protected $description = 'Remove expired private research export artifacts';

    public function handle(): int
    {
        $disk = Storage::disk(config('performance.export_disk', 'local'));
        foreach ($disk->directories('research-exports') as $directory) {
            $date = basename($directory);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date < now()->subDays(2)->format('Y-m-d')) $disk->deleteDirectory($directory);
        }
        return self::SUCCESS;
    }
}
