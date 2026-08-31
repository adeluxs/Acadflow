<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeatureEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\Commerce\LedgerService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Legacy subscription payment compatibility only.
 *
 * New subscription purchase/upgrade actions were intentionally removed. This
 * controller remains solely so a payment started before the monetization
 * cutover can be verified and converted into independent entitlements without
 * creating a new recurring subscription.
 */
class SubscriptionController extends Controller
{
    public function __construct(private PaymentGatewayManager $paymentGatewayManager, private LedgerService $ledger) {}

    public function paymentCallback(string $transactionUuid)
    {
        $transaction = Transaction::query()->where('uuid', $transactionUuid)->firstOrFail();
        abort_unless($transaction->user_id === auth()->id() || auth()->user()?->isAdmin(), 403);

        $gateway = $this->paymentGatewayManager->gatewayForTransaction($transaction);
        $verification = $gateway->verifyPayment((string) $transaction->gateway_transaction_id);

        if (! $this->verifiedForTransaction($transaction, $verification)) {
            $gatewayStatus=strtolower((string)data_get($verification,'data.status','pending'));
            $definitive=in_array($gatewayStatus,['failed','abandoned','reversed','cancelled','canceled'],true);
            $transaction->update([
                'status' => $definitive ? 'failed' : 'processing',
                'gateway_status' => $gatewayStatus,
            ]);

            return redirect()->route('commerce.wallet')
                ->with('error', $definitive
                    ? 'The legacy payment was not completed. No entitlement was created.'
                    : 'The legacy payment is not conclusively verified yet. It remains pending and can be checked again safely.');
        }

        $this->settleLegacyPayment($transaction, $verification);

        return redirect()->route('commerce.wallet')
            ->with('success', 'Your earlier payment was verified. Equivalent access was preserved as non-recurring entitlements.');
    }

    public function webhook(Request $request, string $gatewayCode)
    {
        $gateway = $this->paymentGatewayManager->gateway($gatewayCode);
        $signature = $request->header('X-Paystack-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-Signature');

        if (! $gateway->validateWebhook($request->all(), $signature)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $reference = (string) $request->input('data.reference', '');
        $transaction = Transaction::query()->where('gateway_transaction_id', $reference)->first();
        if (! $transaction) return response()->json(['status' => 'received', 'matched' => false]);

        // This endpoint is retained only for historical subscription payments.
        $metadata = $transaction->metadata ?? [];
        $isLegacySubscription = isset($metadata['plan_id'])
            || $transaction->transactionable_type === UserSubscription::class
            || $transaction->subscriptions()->exists();
        if (! $isLegacySubscription) {
            return response()->json(['status' => 'received', 'matched' => false]);
        }

        $verification = $gateway->verifyPayment($reference);
        if (! $this->verifiedForTransaction($transaction, $verification)) {
            return response()->json(['status' => 'received', 'verified' => false], 202);
        }

        $this->settleLegacyPayment($transaction, $verification);

        return response()->json(['status' => 'success', 'verified' => true, 'recurring_subscription_created' => false]);
    }

    private function verifiedForTransaction(Transaction $transaction, array $verification): bool
    {
        if (! ($verification['status'] ?? false) || ! ($verification['paid'] ?? false)) return false;

        $expectedMinor = $transaction->amount_minor !== null
            ? (int) $transaction->amount_minor
            : Money::toMinor((string) $transaction->amount);
        $providerMinor = (int) data_get($verification, 'data.amount', -1);
        $providerCurrency = strtoupper((string) data_get($verification, 'data.currency', ''));
        $expectedCurrency = strtoupper((string) ($transaction->currency ?: 'NGN'));

        return $providerMinor === $expectedMinor && $providerCurrency === $expectedCurrency;
    }

    private function settleLegacyPayment(Transaction $transaction, array $verification): void
    {
        DB::transaction(function () use ($transaction, $verification): void {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $metadata = $locked->metadata ?? [];
            if (($metadata['legacy_entitlements_migrated'] ?? false) === true) return;

            $subscription = null;
            if ($locked->transactionable_type === UserSubscription::class && $locked->transactionable_id) {
                $subscription = UserSubscription::query()->find($locked->transactionable_id);
            }
            $subscription ??= $locked->subscriptions()->with('plan')->first();

            $planId = $subscription?->plan_id ?? ($metadata['plan_id'] ?? null);
            $plan = $planId ? SubscriptionPlan::query()->find($planId) : null;
            abort_unless($plan, 422, 'The historical plan attached to this payment could not be resolved.');

            $expiresAt = $subscription?->ends_at;
            if (! $expiresAt) {
                $cycle = (string) ($subscription?->billing_cycle ?? $metadata['billing_cycle'] ?? 'monthly');
                $expiresAt = match ($cycle) {
                    'semester' => now()->addMonths(4),
                    'yearly' => now()->addYear(),
                    default => now()->addMonth(),
                };
            }

            foreach ($this->planFeatures($plan) as $feature) {
                FeatureEntitlement::query()->updateOrCreate([
                    'user_id' => $locked->user_id,
                    'feature' => $feature,
                    'source_type' => 'legacy_subscription_payment',
                    'source_id' => $locked->id,
                ], [
                    'access_type' => 'granted',
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'metadata' => [
                        'legacy_plan_id' => $plan->id,
                        'non_recurring' => true,
                        'migration' => '2026_monetization_rebuild',
                    ],
                ]);
            }

            if ($subscription) {
                // Preserve the historical row, but explicitly prevent future renewal.
                $subscription->update([
                    'auto_renew' => false,
                    'payment_status' => 'paid',
                ]);
            }

            $amountMinor=$locked->amount_minor!==null?(int)$locked->amount_minor:Money::toMinor((string)$locked->amount);
            if($amountMinor>0){
                $this->ledger->post('legacy-subscription-payment:'.$locked->uuid,'legacy_entitlement_payment',strtoupper((string)($locked->currency?:'NGN')),[
                    ['account_code'=>'payment_processor_receivable','direction'=>'debit','amount_minor'=>$amountMinor],
                    ['account_code'=>'legacy_entitlement_revenue','direction'=>'credit','amount_minor'=>$amountMinor],
                ],$locked->user,['legacy_transaction_uuid'=>$locked->uuid,'legacy_plan_id'=>$plan->id,'non_recurring'=>true]);
            }

            $locked->update([
                'status' => 'completed',
                'processed_at' => now(),
                'gateway_status' => data_get($verification, 'data.status', 'success'),
                'metadata' => array_merge($metadata, [
                    'legacy_entitlements_migrated' => true,
                    'recurring_subscription_created' => false,
                    'legacy_plan_id' => $plan->id,
                ]),
            ]);
        }, 3);
    }

    /** @return list<string> */
    private function planFeatures(SubscriptionPlan $plan): array
    {
        $features = array_values(array_filter((array) $plan->features, 'is_string'));
        $booleanMap = [
            'allow_group_submissions' => 'group_submissions',
            'allow_rubrics' => 'rubrics',
            'allow_attendance_tracking' => 'attendance_tracking',
            'allow_document_generation' => 'document_generation',
            'allow_api_access' => 'api_access',
            'allow_white_label' => 'white_label',
        ];
        foreach ($booleanMap as $column => $feature) if ((bool) $plan->{$column}) $features[] = $feature;

        return array_values(array_unique($features));
    }
}
