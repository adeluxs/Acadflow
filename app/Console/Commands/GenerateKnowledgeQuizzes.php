<?php

namespace App\Console\Commands;

use App\Jobs\GenerateKnowledgeQuiz;
use App\Models\KnowledgePublication;
use App\Services\Knowledge\QuizBankGenerator;
use Illuminate\Console\Command;

/**
 * Finds published guides that have no AI question bank yet, or whose last attempt failed
 * (up to 3 tries), and queues them. Runs hourly; also usable by hand
 * to give the starter guides their questions.
 */
class GenerateKnowledgeQuizzes extends Command
{
    protected $signature = 'knowledge:generate-quizzes {--limit=25 : Guides to queue in this run} {--now : Generate here instead of queueing}';

    protected $description = 'Queue AI Read & Earn question banks for published Knowledge Hub guides';

    public function handle(QuizBankGenerator $generator): int
    {
        if (! $generator->enabled()) {
            $this->warn('AI questions are switched off in Admin > Knowledge Hub > Read & Earn.');

            return self::SUCCESS;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $queued = 0;
        KnowledgePublication::withoutGlobalScopes()->with('document:id,body')->where('status', 'published')
            // Only guides never given a bank, or failed ones still worth retrying. Edited guides are
            // handled when they are saved, so this never re-reads every guide every hour.
            ->where(fn ($q) => $q->whereNull('quiz_status')->orWhere(fn ($f) => $f->where('quiz_status', 'failed')->where('quiz_attempts', '<', QuizBankGenerator::MAX_ATTEMPTS)))
            ->orderBy('id')->chunkById(100, function ($publications) use ($generator, $limit, &$queued) {
                foreach ($publications as $publication) {
                    if ($queued >= $limit) {
                        return false;
                    }
                    if (! $generator->needsGeneration($publication)) {
                        continue;
                    }
                    $this->option('now') ? $generator->generate($publication) : GenerateKnowledgeQuiz::dispatch($publication->id);
                    $queued++;
                    $this->line(($this->option('now') ? 'Generated: ' : 'Queued: ').$publication->title);
                }
            });
        $this->info(($this->option('now') ? 'Processed ' : 'Queued ').$queued.' guide'.($queued === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
