<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Compatibility tombstone for legacy cron entries.
 *
 * Recurring subscriptions were retired by the 2026 monetization rebuild.
 * Keeping the command name as a no-op prevents an old server cron entry from
 * failing loudly while guaranteeing it can never renew or charge a customer.
 */
class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Deprecated: recurring subscriptions are retired; no charges are performed';

    public function handle(): int
    {
        $this->warn('No action taken: recurring subscriptions have been retired. Remove this legacy cron entry when convenient.');

        return self::SUCCESS;
    }
}
