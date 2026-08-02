<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\Transaction;
use App\Services\SubscriptionService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private PaymentGatewayManager $paymentGatewayManager
    ) {}

    /**
     * Show user's current subscription
     */
    public function show()
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription()->first();
        $plan = $subscription?->plan;

        $summary = $this->subscriptionService->getSubscriptionSummary($user);
        $transactions = Transaction::where('user_id', $user->id)
            ->where('type', 'payment')
            ->latest()
            ->take(5)
            ->get();

        return view('subscription.show', compact('user', 'subscription', 'plan', 'summary', 'transactions'));
    }

    /**
     * Show available plans for upgrade
     */
    public function upgrade()
    {
        $user = Auth::user();
        $currentSubscription = $user->activeSubscription()->first();
        $currentPlan = $currentSubscription?->plan;

        $plans = SubscriptionPlan::where('is_active', true)
            ->where('plan_type', 'b2c')
            ->orderBy('sort_order')
            ->orderBy('price_per_month')
            ->get()
            ->map(function ($plan) use ($currentPlan) {
                $plan->is_current = $currentPlan && $currentPlan->id === $plan->id;
                $plan->monthly_price = $plan->getPriceForCycle('monthly');
                $plan->yearly_price = $plan->getPriceForCycle('yearly');
                return $plan;
            });

        return view('subscription.upgrade', compact('plans', 'currentPlan'));
    }

    /**
     * Show checkout page
     */
    public function checkout(Request $request, $planId)
    {
        $user = Auth::user();
        $plan = SubscriptionPlan::findOrFail($planId);

        if (! $plan->is_active || ! $plan->isAvailable()) {
            return back()->with('error', 'This plan is not available.');
        }

        $billingCycle = $request->input('billing_cycle', 'monthly');
        $amount = $plan->getPriceForCycle($billingCycle);

        // Get available payment gateways
        $gateways = app(PaymentGatewayManager::class)->getAvailableGateways();

        return view('subscription.checkout', compact('user', 'plan', 'billingCycle', 'amount', 'gateways'));
    }

    /**
     * Initiate payment
     */
    public function initiatePayment(Request $request, $planId)
    {
        $request->validate([
            'billing_cycle' => 'required|in:monthly,semester,yearly',
            'gateway' => 'required|string|exists:payment_gateways,code',
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ]);

        $user = Auth::user();
        $plan = SubscriptionPlan::findOrFail($planId);

        if (! $plan->is_active) {
            return back()->with('error', 'This plan is not available.');
        }

        $amount = $plan->getPriceForCycle($request->billing_cycle);

        // Create transaction record
        $transaction = Transaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'payment_gateway_id' => $this->getGatewayId($request->gateway),
            'amount' => $amount,
            'currency' => \App\Services\SettingService::get('currency', 'USD'),
            'type' => 'payment',
            'status' => 'pending',
        ]);

        // Initialize payment with gateway
        $gateway = $this->paymentGatewayManager->gateway($request->gateway);

        $paymentData = [
            'email' => $user->email,
            'amount' => $amount,
            'reference' => $transaction->uuid,
            'currency' => \App\Services\SettingService::get('currency', 'USD'),
            'callback_url' => route('subscription.payment.callback', $transaction->uuid),
            'user_id' => $user->id,
            'metadata' => [
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
            ],
        ];

        $result = $gateway->initializePayment($paymentData);

        if ($result['status']) {
            // Store gateway transaction ID
            $transaction->update([
                'gateway_transaction_id' => $result['reference'] ?? null,
                'metadata' => [
                    'authorization_url' => $result['authorization_url'] ?? null,
                    'access_code' => $result['access_code'] ?? null,
                ],
            ]);

            return redirect()->away($result['authorization_url']);
        }

        return back()->with('error', $result['message'] ?? 'Payment initialization failed');
    }

    /**
     * Payment callback handler
     */
    public function paymentCallback($transactionUuid)
    {
        $transaction = Transaction::where('uuid', $transactionUuid)->firstOrFail();

        // Verify payment with gateway
        $gateway = $this->paymentGatewayManager->gatewayForTransaction($transaction);
        $verification = $gateway->verifyPayment($transaction->gateway_transaction_id);

        if ($verification['status'] && $verification['paid']) {
            // Payment verified, activate subscription
            DB::transaction(function () use ($transaction, $verification) {
                $transaction->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'gateway_status' => $verification['data']['status'] ?? null,
                ]);

                $plan = SubscriptionPlan::findOrFail($transaction->metadata['plan_id']);
                $billingCycle = $transaction->metadata['billing_cycle'];
                $user = $transaction->user;

                // Cancel existing subscription
                $user->activeSubscription()->update(['status' => 'cancelled']);

                // Create new subscription
                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'billing_cycle' => $billingCycle,
                    'payment_status' => 'paid',
                    'payment_reference' => $transaction->uuid,
                    'gateway' => $transaction->paymentGateway->code,
                    'auto_renew' => true,
                    'started_at' => now(),
                    'ends_at' => $this->calculateEndDate($billingCycle),
                    'trial_ends_at' => $plan->has_trial ? now()->addDays($plan->trial_days) : null,
                    'payment_method' => 'online',
                ]);

                // Link transaction to subscription
                $subscription->transactions()->create([
                    'transaction_id' => $transaction->id,
                    'description' => 'Subscription activation payment',
                ]);

                // Send notification
                $user->notify(new \App\Notifications\SubscriptionActivated($subscription));
            });

            return redirect()->route('subscription.show')
                ->with('success', 'Payment successful! Your subscription has been activated.');
        }

        // Payment failed
        $transaction->update([
            'status' => 'failed',
            'gateway_status' => $verification['data']['status'] ?? 'failed',
        ]);

        return redirect()->route('subscription.checkout', $transaction->metadata['plan_id'])
            ->with('error', 'Payment verification failed. Please try again.');
    }

    /**
     * Webhook handler
     */
    public function webhook(Request $request, $gatewayCode)
    {
        $gateway = $this->paymentGatewayManager->gateway($gatewayCode);

        // Validate webhook signature
        if (! $gateway->validateWebhook($request->all(), $request->header('X-Signature'))) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        // Process webhook
        $reference = $request->input('data.reference');
        $transaction = Transaction::where('gateway_transaction_id', $reference)->first();

        if (! $transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        // Update transaction status
        $verifyResult = $gateway->verifyPayment($reference);

        if ($verifyResult['status'] && $verifyResult['paid']) {
            // Payment completed
            $transaction->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            // Activate subscription if not already active
            $this->activateSubscriptionFromTransaction($transaction);
        } else {
            $transaction->update(['status' => 'failed']);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process upgrade
     */
    public function processUpgrade(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'sometimes|in:monthly,semester,yearly',
        ]);

        $user = Auth::user();
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        if (! $plan->is_active || ! $plan->isAvailable()) {
            return back()->with('error', 'This plan is not available.');
        }

        $billingCycle = $validated['billing_cycle'] ?? 'monthly';
        $amount = $plan->getPriceForCycle($billingCycle);

        // If amount is 0 (free plan), activate directly
        if ($amount == 0) {
            DB::transaction(function () use ($user, $plan, $billingCycle, $amount) {
                $user->activeSubscription()->update(['status' => 'cancelled']);

                UserSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'amount' => $amount,
                    'currency' => 'USD',
                    'billing_cycle' => $billingCycle,
                    'payment_status' => 'paid',
                    'payment_reference' => 'FREE-' . strtoupper(Str::random(10)),
                    'gateway' => 'free',
                    'auto_renew' => false,
                    'started_at' => now(),
                    'ends_at' => null, // No expiry for free plans
                    'trial_ends_at' => null,
                    'payment_method' => 'none',
                ]);
            });

            return redirect()->route('subscription.show')
                ->with('success', 'Successfully subscribed to ' . $plan->display_name);
        }

        // Redirect to checkout for paid plans
        return redirect()->route('subscription.checkout', [
            'plan' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel($subscriptionId)
    {
        $user = Auth::user();
        $subscription = UserSubscription::where('user_id', $user->id)->findOrFail($subscriptionId);

        $subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
        ]);

        return back()->with('success', 'Subscription cancelled successfully.');
    }

    /**
     * Calculate subscription amount based on plan and billing cycle
     */
    protected function calculateAmount(SubscriptionPlan $plan, string $billingCycle, ?float $overrideAmount = null): float
    {
        return $plan->getPriceForCycle($billingCycle);
    }

    /**
     * Calculate subscription end date based on billing cycle
     */
    protected function calculateEndDate(string $billingCycle)
    {
        return match ($billingCycle) {
            'semester' => now()->addMonths(4),
            'yearly' => now()->addMonths(10),
            default => now()->addMonth(),
        };
    }

    /**
     * Activate subscription from transaction
     */
    protected function activateSubscriptionFromTransaction(Transaction $transaction)
    {
        if ($transaction->transactionable_type === UserSubscription::class) {
            $subscription = UserSubscription::find($transaction->transactionable_id);
            if ($subscription && $subscription->status !== 'active') {
                $subscription->update(['status' => 'active']);
            }
        }
    }

    private function getGatewayId(string $code): ?int
    {
        return \App\Models\PaymentGateway::where('code', $code)->value('id');
    }
}

