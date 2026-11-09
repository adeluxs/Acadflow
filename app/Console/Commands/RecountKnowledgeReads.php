<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs read counts that were inflated before reads were counted once per person.
 * Signed-in readers are rebuilt from the view history (one read each). Old visits by
 * people who were not signed in can't be told apart, so they are left out unless
 * --keep-guest-views is given (then each old guest visit stays as one read).
 */
class RecountKnowledgeReads extends Command
{
    protected $signature = 'knowledge:recount-reads {--apply : Save the corrected counts (without this it only reports)} {--keep-guest-views : Keep old signed-out visits in the count}';

    protected $description = 'Recalculate Knowledge Hub read counts as one read per reader';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;
        $before = 0;
        $after = 0;

        DB::table('knowledge_publications')->select(['id', 'title', 'view_count'])->orderBy('id')->chunkById(200, function ($publications) use ($apply, &$changed, &$before, &$after): void {
            $ids = $publications->pluck('id')->all();
            $readers = DB::table('knowledge_events')->where('event_type', 'view')->whereIn('knowledge_publication_id', $ids)->whereNotNull('user_id')
                ->select(['knowledge_publication_id', 'user_id'])->distinct()->get()->groupBy('knowledge_publication_id');
            $guests = $this->option('keep-guest-views')
                ? DB::table('knowledge_events')->where('event_type', 'view')->whereIn('knowledge_publication_id', $ids)->whereNull('user_id')
                    ->selectRaw('knowledge_publication_id, COUNT(*) AS total')->groupBy('knowledge_publication_id')->pluck('total', 'knowledge_publication_id')
                : collect();

            foreach ($publications as $publication) {
                $userIds = ($readers[$publication->id] ?? collect())->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                if ($apply) {
                    DB::table('knowledge_publication_reads')->insertOrIgnore(array_map(fn ($userId) => ['knowledge_publication_id' => $publication->id, 'user_id' => $userId, 'visitor_key' => null, 'created_at' => now()], $userIds));
                    $userIds = DB::table('knowledge_publication_reads')->where('knowledge_publication_id', $publication->id)->whereNotNull('user_id')->pluck('user_id')->all();
                }
                $newGuests = DB::table('knowledge_publication_reads')->where('knowledge_publication_id', $publication->id)->whereNull('user_id')->count();
                $count = count($userIds) + $newGuests + (int) ($guests[$publication->id] ?? 0);
                $before += (int) $publication->view_count;
                $after += $count;
                if ($count !== (int) $publication->view_count) {
                    $changed++;
                    $this->line(sprintf('#%d %s: %d → %d', $publication->id, mb_strimwidth((string) $publication->title, 0, 60, '…'), $publication->view_count, $count));
                    if ($apply) {
                        DB::table('knowledge_publications')->where('id', $publication->id)->update(['view_count' => $count]);
                    }
                }
            }
        });

        $this->info("Publications changed: {$changed}. Total reads {$before} → {$after}.");
        if (! $apply) {
            $this->warn('Nothing saved. Run again with --apply to save.');
        }

        return self::SUCCESS;
    }
}
