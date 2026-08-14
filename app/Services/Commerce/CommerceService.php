<?php

namespace App\Services\Commerce;

use App\Models\CommerceEntitlement;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceRefund;
use App\Models\CommerceRevenueAllocation;
use App\Models\KnowledgePublication;
use App\Models\LearningPath;
use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommerceService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly WalletService $wallets,
    ) {}

    public function createOrder(User $buyer, Model $purchasable, array $billing = []): CommerceOrder
    {
        $this->assertPurchasable($purchasable, $buyer);
        $price = round((float) ($purchasable->price ?? 0), 2);
        if ($price <= 0) {
            throw ValidationException::withMessages(['item' => 'This item does not require purchase.']);
        }

        return DB::transaction(function () use ($buyer, $purchasable, $billing, $price) {
            $order = CommerceOrder::create([
                'university_id' => $buyer->university_id,
                'buyer_id' => $buyer->id,
                'order_number' => 'AF-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)),
                'currency' => 'NGN',
                'subtotal' => $price,
                'total_amount' => $price,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'billing_details' => $billing,
                'metadata' => ['source' => 'knowledge_hub'],
            ]);
            $order->items()->create([
                'purchasable_type' => $purchasable->getMorphClass(),
                'purchasable_id' => $purchasable->getKey(),
                'seller_id' => $purchasable->creator_id ?? null,
                'title' => $purchasable->title,
                'quantity' => 1,
                'unit_price' => $price,
                'total_price' => $price,
                'metadata' => ['access_type' => $purchasable->access_type ?? 'premium'],
            ]);
            return $order->fresh('items');
        });
    }

    public function initialize(CommerceOrder $order, string $gatewayCode, string $callbackUrl): array
    {
        abort_unless($order->payment_status === 'unpaid' && $order->status === 'pending', 422, 'This order cannot be paid.');
        $gatewayModel = PaymentGateway::query()->where('code', $gatewayCode)->where('is_active', true)->firstOrFail();
        $gateway = $this->gateways->gateway($gatewayCode);
        abort_unless($gateway->isConfigured(), 503, 'The selected payment gateway is not configured.');

        $transaction = Transaction::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $order->buyer_id,
            'payment_gateway_id' => $gatewayModel->id,
            'transactionable_type' => $order->getMorphClass(),
            'transactionable_id' => $order->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'type' => 'payment',
            'status' => 'pending',
            'gateway_transaction_id' => 'AFC-'.strtoupper(Str::random(18)),
            'metadata' => ['order_number' => $order->order_number],
        ]);
        $order->update(['transaction_id' => $transaction->id, 'status' => 'payment_pending']);

        $callbackUrl = str_replace(['{transaction}', 'TRANSACTION_UUID'], $transaction->uuid, $callbackUrl);
        $result = $gateway->initializePayment([
            'email' => $order->buyer->email,
            'amount' => (float) $order->total_amount,
            'reference' => $transaction->gateway_transaction_id,
            'currency' => $order->currency,
            'callback_url' => $callbackUrl,
            'user_id' => $order->buyer_id,
            'order_id' => $order->id,
            'type' => 'knowledge_marketplace',
            'metadata' => ['order_uuid' => $order->uuid, 'transaction_uuid' => $transaction->uuid],
        ]);

        if (! ($result['status'] ?? false)) {
            $transaction->update(['status' => 'failed', 'gateway_status' => 'initialization_failed', 'notes' => $result['message'] ?? 'Gateway initialization failed.']);
            $order->update(['status' => 'payment_failed']);
            throw ValidationException::withMessages(['payment' => $result['message'] ?? 'Payment initialization failed.']);
        }

        $transaction->update(['status' => 'processing', 'gateway_status' => 'initialized', 'metadata' => array_merge($transaction->metadata ?? [], ['access_code' => $result['access_code'] ?? null])]);
        return ['order' => $order->fresh(), 'transaction' => $transaction->fresh(), 'authorization_url' => $result['authorization_url'] ?? null];
    }

    public function verify(Transaction $transaction): CommerceOrder
    {
        $transaction->loadMissing('paymentGateway', 'transactionable');
        abort_unless($transaction->type === 'payment' && $transaction->transactionable instanceof CommerceOrder, 422);
        if ($transaction->status === 'completed') {
            return $transaction->transactionable->fresh('items');
        }
        $gateway = $this->gateways->gateway($transaction->paymentGateway?->code);
        $result = $gateway->verifyPayment((string) $transaction->gateway_transaction_id);
        abort_unless(($result['status'] ?? false) && ($result['paid'] ?? false), 422, 'Payment has not been confirmed by the gateway.');

        $data = $result['data'] ?? [];
        $paidAmount = isset($data['amount']) ? ((float) $data['amount'] / 100) : (float) $transaction->amount;
        abort_unless(abs($paidAmount - (float) $transaction->amount) < 0.01, 422, 'Gateway amount does not match the order total.');
        abort_unless(strtoupper((string) ($data['currency'] ?? $transaction->currency)) === strtoupper($transaction->currency), 422, 'Gateway currency does not match the order.');

        return $this->complete($transaction, $data);
    }

    public function complete(Transaction $transaction, array $gatewayData = []): CommerceOrder
    {
        return DB::transaction(function () use ($transaction, $gatewayData) {
            $transaction = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            /** @var CommerceOrder $order */
            $order = CommerceOrder::query()->whereKey($transaction->transactionable_id)->lockForUpdate()->firstOrFail();
            if ($transaction->status === 'completed') {
                return $order->fresh('items');
            }
            $transaction->update(['status' => 'completed', 'gateway_status' => $gatewayData['status'] ?? 'success', 'processed_at' => now(), 'metadata' => array_merge($transaction->metadata ?? [], ['gateway' => $gatewayData])]);
            $order->update(['status' => 'completed', 'payment_status' => 'paid', 'paid_at' => now(), 'transaction_id' => $transaction->id]);

            foreach ($order->items()->with('purchasable')->get() as $item) {
                CommerceEntitlement::updateOrCreate(
                    ['user_id' => $order->buyer_id, 'entitled_type' => $item->purchasable_type, 'entitled_id' => $item->purchasable_id],
                    ['commerce_order_item_id' => $item->id, 'access_level' => 'full', 'status' => 'active', 'starts_at' => now(), 'revoked_at' => null, 'metadata' => ['order_uuid' => $order->uuid]]
                );
                $this->allocate($item);
            }
            return $order->fresh(['items.entitlements', 'transaction']);
        });
    }

    public function requestRefund(CommerceOrder $order, User $requester, float $amount, string $reason): CommerceRefund
    {
        abort_unless($order->buyer_id === $requester->id || $requester->isAdmin(), 403);
        abort_unless(in_array($order->payment_status, ['paid', 'partially_refunded'], true), 422, 'Only paid orders can be refunded.');
        $alreadyRequested = (float) $order->refunds()->whereIn('status', ['requested', 'completed'])->sum('amount');
        abort_unless($amount > 0 && ($alreadyRequested + $amount) <= (float) $order->total_amount + 0.001, 422, 'Refund amount exceeds the unrefunded order balance.');
        return $order->refunds()->create(['requested_by' => $requester->id, 'amount' => $amount, 'reason' => $reason, 'status' => 'requested']);
    }

    public function processRefund(CommerceRefund $refund, User $actor, bool $approve, ?string $note = null): CommerceRefund
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($refund, $actor, $approve, $note) {
            $refund = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->status === 'requested', 422, 'Refund already processed.');
            if (! $approve) {
                $refund->update(['status' => 'rejected', 'processed_by' => $actor->id, 'decision_note' => $note, 'processed_at' => now()]);
                return $refund->fresh();
            }
            $order = $refund->order()->with('transaction.paymentGateway', 'items.entitlements', 'items.revenueAllocations')->firstOrFail();
            $gateway = $this->gateways->gateway($order->transaction?->paymentGateway?->code);
            $result = $gateway->refund((string) $order->transaction?->gateway_transaction_id, (float) $refund->amount);
            abort_unless($result['status'] ?? false, 422, $result['message'] ?? 'Gateway refund failed.');

            $completedBefore = (float) $order->refunds()->where('status', 'completed')->where('id', '!=', $refund->id)->sum('amount');
            $completedAfter = $completedBefore + (float) $refund->amount;
            $full = $completedAfter >= (float) $order->total_amount - 0.01;
            $ratio = min(1.0, (float) $refund->amount / max(0.01, (float) $order->total_amount));

            foreach ($order->items as $item) {
                if ($full) {
                    $item->entitlements()->update(['status' => 'revoked', 'revoked_at' => now()]);
                }
                foreach ($item->revenueAllocations()->whereIn('status', ['pending', 'released', 'settled', 'partially_reversed'])->get() as $allocation) {
                    $meta = $allocation->metadata ?? [];
                    $alreadyReversed = (float) ($meta['reversed_amount'] ?? 0);
                    $targetReversal = $full
                        ? (float) $allocation->amount
                        : min((float) $allocation->amount, round((float) $allocation->amount * $ratio, 2));
                    $reversal = $full
                        ? max(0, round($targetReversal - $alreadyReversed, 2))
                        : max(0, round($targetReversal, 2));
                    if ($reversal <= 0) continue;
                    if ($allocation->beneficiary_user_id && in_array($allocation->status, ['released', 'partially_reversed'], true)) {
                        $this->wallets->debitReversal($allocation->beneficiaryUser, $reversal, 'refund_reversal', $refund, 'Sale earnings reversed after refund.', [
                            'allocation_id' => $allocation->id,
                            'refund_uuid' => $refund->uuid,
                        ]);
                    }
                    $totalReversed = min((float) $allocation->amount, $alreadyReversed + $reversal);
                    $allocation->update([
                        'status' => $totalReversed >= (float) $allocation->amount - 0.01 ? 'reversed' : 'partially_reversed',
                        'metadata' => array_merge($meta, ['reversed_amount' => $totalReversed]),
                    ]);
                }
            }

            $order->update([
                'status' => $full ? 'refunded' : 'partially_refunded',
                'payment_status' => $full ? 'refunded' : 'partially_refunded',
            ]);
            if ($full) $order->transaction?->update(['status' => 'refunded']);
            $refund->update(['status' => 'completed', 'processed_by' => $actor->id, 'gateway_refund_id' => data_get($result, 'data.id'), 'decision_note' => $note, 'processed_at' => now()]);
            return $refund->fresh();
        });
    }

    public function requestWithdrawal(User $user, int $payoutAccountId, float $amount): WithdrawalRequest
    {
        $wallet = $this->wallets->account($user);
        $payout = $user->payoutAccounts()->whereKey($payoutAccountId)->where('is_verified', true)->firstOrFail();
        $minimum = (float) SettingService::get('minimum_withdrawal_amount', 1000);
        abort_unless($amount >= $minimum, 422, 'Amount is below the configured withdrawal minimum.');

        return DB::transaction(function () use ($wallet, $payout, $amount, $user) {
            $wallet = $wallet->newQuery()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            abort_unless((float) $wallet->available_balance >= $amount, 422, 'Insufficient wallet balance.');
            $feePercentage = (float) SettingService::get('withdrawal_fee_percentage', 0);
            $fee = round($amount * $feePercentage / 100, 2);
            $this->wallets->debit($user, $amount, 'withdrawal_hold', $payout, null, 'Funds reserved for withdrawal.');
            return WithdrawalRequest::create(['wallet_account_id' => $wallet->id, 'payout_account_id' => $payout->id, 'amount' => $amount, 'fee' => $fee, 'status' => 'pending']);
        });
    }

    public function processWithdrawal(WithdrawalRequest $withdrawal, User $actor, string $decision, ?string $reference = null, ?string $note = null): WithdrawalRequest
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($withdrawal, $actor, $decision, $reference, $note) {
            $withdrawal = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->with('wallet.user')->firstOrFail();
            abort_unless($withdrawal->status === 'pending', 422, 'Withdrawal already processed.');
            if ($decision === 'reject') {
                $this->wallets->credit($withdrawal->wallet->user, (float) $withdrawal->amount, 'withdrawal_release', $withdrawal, null, 'Rejected withdrawal funds returned.');
                $withdrawal->update(['status' => 'rejected', 'processed_by' => $actor->id, 'note' => $note, 'processed_at' => now()]);
            } elseif ($decision === 'approve') {
                abort_unless($reference, 422, 'A provider or bank reference is required.');
                $withdrawal->update(['status' => 'paid', 'provider_reference' => $reference, 'processed_by' => $actor->id, 'note' => $note, 'processed_at' => now()]);
            } else {
                throw ValidationException::withMessages(['decision' => 'Invalid withdrawal decision.']);
            }
            return $withdrawal->fresh();
        });
    }

    private function allocate(CommerceOrderItem $item): void
    {
        if ($item->revenueAllocations()->exists()) return;
        $platformPercent = min(100, max(0, (float) SettingService::get('knowledge_platform_commission_percentage', 15)));
        $institutionPercent = min(100 - $platformPercent, max(0, (float) SettingService::get('knowledge_institution_revenue_percentage', 0)));
        $creatorPercent = 100 - $platformPercent - $institutionPercent;
        $total = (float) $item->total_price;
        $rows = [
            ['allocation_type' => 'platform', 'percentage' => $platformPercent, 'amount' => round($total * $platformPercent / 100, 2)],
            ['allocation_type' => 'institution', 'beneficiary_university_id' => $item->order->university_id, 'percentage' => $institutionPercent, 'amount' => round($total * $institutionPercent / 100, 2)],
            ['allocation_type' => 'creator', 'beneficiary_user_id' => $item->seller_id, 'percentage' => $creatorPercent, 'amount' => round($total * $creatorPercent / 100, 2)],
        ];
        foreach ($rows as $row) {
            if ((float) $row['amount'] <= 0) continue;
            $allocation = $item->revenueAllocations()->create(array_merge($row, ['status' => $row['allocation_type'] === 'creator' ? 'released' : 'settled', 'released_at' => now()]));
            if ($allocation->beneficiary_user_id) {
                $this->wallets->credit($allocation->beneficiaryUser, (float) $allocation->amount, 'sale_earning', $allocation, null, 'Knowledge Hub sale earnings.');
            }
        }
    }

    private function assertPurchasable(Model $purchasable, User $buyer): void
    {
        $supported = $purchasable instanceof KnowledgePublication || $purchasable instanceof LearningPath;
        abort_unless($supported, 422, 'Unsupported marketplace item.');
        $published = $purchasable instanceof KnowledgePublication ? $purchasable->isPublished() : $purchasable->status === 'published';
        abort_unless($published && $purchasable->access_type === 'premium', 422, 'This item is not available for purchase.');
        abort_if($purchasable->creator_id === $buyer->id, 422, 'Creators already have access to their own item.');
        abort_if($buyer->hasEntitlement($purchasable), 422, 'You already own this item.');
    }
}
