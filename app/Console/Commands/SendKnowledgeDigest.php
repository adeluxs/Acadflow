<?php

namespace App\Console\Commands;

use App\Services\Knowledge\KnowledgeNotificationService;
use Illuminate\Console\Command;

class SendKnowledgeDigest extends Command
{
    protected $signature = 'knowledge:weekly-digest {--force : Send now, ignoring the configured day and hour}';

    protected $description = 'Queue the weekly "New on the Knowledge Hub" roundup when it is due';

    public function handle(KnowledgeNotificationService $service): int
    {
        $result = $service->dispatchDigest((bool) $this->option('force'));
        $this->info('Weekly roundup: '.$result['status'].(isset($result['publications']) ? ' ('.$result['publications'].' new publications)' : '').(isset($result['batches']) ? ', '.$result['batches'].' batches queued' : '').'.');

        return self::SUCCESS;
    }
}
