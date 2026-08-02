<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

class SubscriptionProrationService
{
    /**
     * Calculate prorated amount for plan upgrade/downgrade
     */
    public function calculateProratedAmount(UserSubscription $currentSubscription, SubscriptionPlan $newPlan, string $billingCycle): float
    {
        $currentPlan = $currentSubscription->plan;
        if (!$currentPlan) {
            return $newPlan->getPriceForCycle($billingCycle);
        }

        $currentPrice = $currentSubscription->amount;
        $newPrice = $newPlan->getPriceForCycle($billingCycle);

        // Calculate remaining time on current subscription
        $totalDays = $this->getBillingCycleDays($currentSubscription->billing_cycle);
        $remainingDays = now()->diffInDays($currentSubscription->ends_at);
        $elapsedRatio = 1 - ($remainingDays / max(1, $totalDays));

        // Calculate used value of current subscription
        $usedValue = $currentPrice * $elapsedRatio;

        // Apply proration: new price minus unused portion of old price
        $unusedValue = $currentPrice - $usedValue;
        $proratedAmount = max(0, $newPrice - $unusedValue);

        // Round to nearest cent
        return round($proratedAmount, 2);
    }

    /**
     * Get days in billing cycle
     */
    protected function getBillingCycleDays(?string $billingCycle): int
    {
        return match ($billingCycle) {
            'monthly' => 30,
            'semester' => 120,
            'yearly' => 365,
            default => 30,
        };
    }

    /**
     * Calculate refund amount for cancellation
     */
    public function calculateRefundAmount(UserSubscription $subscription): float
    {
        if (!$subscription->plan || $subscription->plan->refundable === false) {
            return 0;
        }

        // Check if within refund period
        if ($subscription->plan->refund_period_days > 0) {
            $daysSinceStart = now()->diffInDays($subscription->started_at);
            if ($daysSinceStart > $subscription->plan->refund_period_days) {
                return 0;
            }
        }

        // Calculate pro-rated refund for remaining time
        $totalDays = $this->getBillingCycleDays($subscription->billing_cycle);
        $remainingDays = now()->diffInDays($subscription->ends_at);
        $refundRatio = $remainingDays / max(1, $totalDays);

        return round($subscription->amount * $refundRatio, 2);
    }

    /**
     * Auto-renew subscription
     */
    public function renewSubscription(UserSubscription $subscription): UserSubscription
    {
        $plan = $subscription->plan;
        if (!$plan) {
            throw new \Exception('Subscription plan not found');
        }

        $billingCycle = $subscription->billing_cycle;
        $newEndDate = match ($billingCycle) {
            'semester' => $subscription->ends_at->copy()->addMonths(4),
            'yearly' => $subscription->ends_at->copy()->addMonths(10),
            default => $subscription->ends_at->copy()->addMonth(),
        };

        // Update subscription
        $subscription->update([
            'status' => 'active',
            'ends_at' => $newEndDate,
            'payment_status' => 'pending',
        ]);

        // Create renewal transaction
        $transaction = \App\Models\Transaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $subscription->user_id,
            'payment_gateway_id' => $subscription->gateway ? \App\Models\PaymentGateway::where('code', $subscription->gateway)->value('id') : null,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'type' => 'payment',
            'status' => 'pending',
            'gateway_status' => 'renewal',
        ]);

        // Link to subscription
        $subscription->transactions()->create([
            'transaction_id' => $transaction->id,
            'description' => 'Auto-renewal payment',
        ]);

        return $subscription;
    }

    /**
     * Check if subscription should auto-renew
     */
    public function shouldAutoRenew(UserSubscription $subscription): bool
    {
        return $subscription->auto_renew && 
               $subscription->status === 'active' &&
               now()->gte($subscription->ends_at->copy()->subDays(7));
    }
}
