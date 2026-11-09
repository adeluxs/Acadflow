<?php

namespace App\Console\Commands;

use App\Services\Knowledge\KnowledgeCategoryMerger;
use Illuminate\Console\Command;

class MergeDuplicateKnowledgeCategories extends Command
{
    protected $signature = 'knowledge:merge-duplicate-categories {--apply : Make the changes (without this it only reports)}';

    protected $description = 'Merge Knowledge Hub categories that appear more than once (nothing is deleted)';

    public function handle(KnowledgeCategoryMerger $merger): int
    {
        $plan = $merger->plan();
        if ($plan->isEmpty()) {
            $this->info('No duplicate categories found.');

            return self::SUCCESS;
        }
        foreach ($plan as ['keep' => $keep, 'extras' => $extras]) {
            $this->line(sprintf('"%s": keep #%d, merge %d cop%s', $keep->name, $keep->id, $extras->count(), $extras->count() === 1 ? 'y' : 'ies'));
        }
        if (! $this->option('apply')) {
            $this->warn('Nothing changed. Run again with --apply to merge.');

            return self::SUCCESS;
        }
        $merged = $merger->merge();
        $this->info("Merged {$merged} duplicate categor".($merged === 1 ? 'y' : 'ies').'. Content now sits under the kept category; the copies are switched off, not deleted.');

        return self::SUCCESS;
    }
}
