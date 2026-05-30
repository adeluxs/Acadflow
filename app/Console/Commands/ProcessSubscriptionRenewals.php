<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use App\Services\SubscriptionProrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Process automatic subscription renewals';

    public function handle(SubscriptionProrationService $prorationService)
    {
        $this->info('Processing subscription renewals...');

        $subscriptions = UserSubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('ends_at', '<=', now()->addDays(7))
            ->where('ends_at', '>', now())
            ->with('plan', 'user')
            ->get();

        $this->info("Found {$subscriptions->count()} subscriptions to renew.");

        $renewed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                DB::transaction(function () use ($subscription, $prorationService) {
                    $prorationService->renewSubscription($subscription);
                    
                    // Send renewal notification
                    $subscription->user->notify(new \App\Notifications\SubscriptionRenewed($subscription));
                    
                    $this->info("Renewed subscription {$subscription->id} for user {$subscription->user->email}");
                });
                
                $renewed++;
            } catch (\Exception $e) {
                $this->error("Failed to renew subscription {$subscription->id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Renewal complete: {$renewed} renewed, {$failed} failed.");
        
        return 0;
    }
}
