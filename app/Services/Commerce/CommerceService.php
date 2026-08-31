<?php

declare(strict_types=1);

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
use App\Models\WalletFundingRequest;
use App\Models\WithdrawalRequest;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommerceService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly WalletService $wallets,
        private readonly IdempotencyService $idempotency,
        private readonly LedgerService $ledger,
    ) {}

    public function createOrder(User $buyer, Model $purchasable, array $billing = [], ?string $idempotencyKey = null): CommerceOrder
    {
        $this->assertPurchasable($purchasable, $buyer);
        $priceMinor = Money::toMinor((string) ($purchasable->price ?? '0'));
        if ($priceMinor <= 0) throw ValidationException::withMessages(['item' => 'This item does not require purchase.']);

        $currency=strtoupper((string)SettingService::get('currency','NGN',$buyer->university_id));
        $create = function () use ($buyer,$purchasable,$billing,$priceMinor,$currency): CommerceOrder {
            return DB::transaction(function () use ($buyer,$purchasable,$billing,$priceMinor,$currency) {
                $decimal=Money::fromMinor($priceMinor);
                $order=CommerceOrder::create([
                    'university_id'=>$buyer->university_id,'buyer_id'=>$buyer->id,'order_number'=>'AF-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)),
                    'currency'=>$currency,'subtotal'=>$decimal,'subtotal_minor'=>$priceMinor,'discount_amount'=>'0.00','discount_amount_minor'=>0,'tax_amount'=>'0.00','tax_amount_minor'=>0,
                    'total_amount'=>$decimal,'total_amount_minor'=>$priceMinor,'status'=>'pending','payment_status'=>'unpaid','billing_details'=>$billing,'metadata'=>['source'=>'knowledge_hub'],
                ]);
                $order->items()->create([
                    'purchasable_type'=>$purchasable->getMorphClass(),'purchasable_id'=>$purchasable->getKey(),'seller_id'=>$purchasable->creator_id??null,'title'=>$purchasable->title,
                    'quantity'=>1,'unit_price'=>$decimal,'unit_price_minor'=>$priceMinor,'total_price'=>$decimal,'total_price_minor'=>$priceMinor,'metadata'=>['access_type'=>$purchasable->access_type??'premium'],
                ]);
                return $order->fresh('items');
            },3);
        };

        if ($idempotencyKey === null) return $create();
        $result = $this->idempotency->execute('commerce.create_order',$idempotencyKey,[
            'buyer_id'=>$buyer->id,
            'purchasable_type'=>$purchasable->getMorphClass(),
            'purchasable_id'=>$purchasable->getKey(),
            'amount_minor'=>$priceMinor,
        ],$buyer,$create);
        if ($result instanceof CommerceOrder) return $result;
        $id=(int)($result['id']??0);
        return CommerceOrder::query()->with('items')->whereKey($id)->where('buyer_id',$buyer->id)->firstOrFail();
    }

    public function initialize(CommerceOrder $order, string $gatewayCode): array
    {
        $order->loadMissing('transaction.paymentGateway');
        if ($order->payment_status==='unpaid' && $order->status==='payment_pending' && $order->transaction?->status==='processing') {
            $existingUrl=(string)($order->transaction->metadata['authorization_url']??'');
            $existingGateway=$order->transaction->paymentGateway?->code;
            if ($existingUrl!=='' && $existingGateway===$gatewayCode) {
                return ['order'=>$order,'transaction'=>$order->transaction,'authorization_url'=>$existingUrl];
            }
        }
        abort_unless($order->payment_status==='unpaid' && $order->status==='pending',422,'This order cannot be paid.');
        $gatewayModel=PaymentGateway::query()->where('code',$gatewayCode)->where('is_active',true)->firstOrFail();
        $gateway=$this->gateways->gateway($gatewayCode);
        abort_unless($gateway->isConfigured(),503,'The selected payment gateway is not configured.');
        $amountMinor=$this->orderMinor($order);

        $transaction=Transaction::create([
            'uuid'=>(string)Str::uuid(),'user_id'=>$order->buyer_id,'payment_gateway_id'=>$gatewayModel->id,'transactionable_type'=>$order->getMorphClass(),'transactionable_id'=>$order->id,
            'amount'=>Money::fromMinor($amountMinor),'amount_minor'=>$amountMinor,'currency'=>$order->currency,'type'=>'payment','status'=>'pending','gateway_transaction_id'=>'AFC-'.strtoupper(Str::random(18)),
            'metadata'=>['order_number'=>$order->order_number,'payment_source'=>'gateway'],
        ]);
        $order->update(['transaction_id'=>$transaction->id,'status'=>'payment_pending']);

        $result=$gateway->initializePayment([
            'email'=>$order->buyer->email,'amount_minor'=>$amountMinor,'amount'=>Money::fromMinor($amountMinor),'reference'=>$transaction->gateway_transaction_id,'currency'=>$order->currency,
            'callback_url'=>route('commerce.callback',$transaction),'user_id'=>$order->buyer_id,'order_id'=>$order->id,'type'=>'knowledge_marketplace',
            'metadata'=>['order_uuid'=>$order->uuid,'transaction_uuid'=>$transaction->uuid],
        ]);
        if(!($result['status']??false)){
            $transaction->update(['status'=>'failed','gateway_status'=>'initialization_failed','notes'=>$result['message']??'Gateway initialization failed.']);
            $order->update(['status'=>'payment_failed']);
            throw ValidationException::withMessages(['payment'=>$result['message']??'Payment initialization failed.']);
        }
        $transaction->update(['status'=>'processing','gateway_status'=>'initialized','metadata'=>array_merge($transaction->metadata??[],['access_code'=>$result['access_code']??null,'authorization_url'=>$result['authorization_url']??null])]);
        return ['order'=>$order->fresh(),'transaction'=>$transaction->fresh(),'authorization_url'=>$result['authorization_url']??null];
    }

    public function purchaseWithWallet(CommerceOrder $order, User $buyer, string $idempotencyKey): CommerceOrder
    {
        abort_unless($order->buyer_id===$buyer->id,403);
        $result=$this->idempotency->execute('commerce.wallet_purchase',$idempotencyKey,['order_uuid'=>$order->uuid,'amount_minor'=>$this->orderMinor($order)],$buyer,function() use($order,$buyer){
            return DB::transaction(function() use($order,$buyer){
                $locked=CommerceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if($locked->payment_status==='paid') return $locked;
                abort_unless(in_array($locked->status,['pending','payment_failed'],true),422,'This order cannot be paid from the wallet.');
                $amountMinor=$this->orderMinor($locked);
                $wallet=$this->wallets->account($buyer,$locked->currency);
                abort_unless(strtoupper((string)$wallet->currency)===strtoupper((string)$locked->currency),422,'Wallet currency does not match this order.');
                $transaction=Transaction::create([
                    'uuid'=>(string)Str::uuid(),'user_id'=>$buyer->id,'transactionable_type'=>$locked->getMorphClass(),'transactionable_id'=>$locked->id,
                    'amount'=>Money::fromMinor($amountMinor),'amount_minor'=>$amountMinor,'currency'=>$locked->currency,'type'=>'payment','status'=>'processing',
                    'gateway_transaction_id'=>'WALLET-'.strtoupper(Str::random(18)),'gateway_status'=>'internal','metadata'=>['payment_source'=>'wallet','order_number'=>$locked->order_number],
                ]);
                $this->wallets->debitSpending($buyer,$amountMinor,'marketplace_purchase',$locked,$transaction,'Knowledge Hub purchase.',['order_uuid'=>$locked->uuid]);
                $locked->update(['transaction_id'=>$transaction->id,'status'=>'payment_pending']);
                return $this->complete($transaction,['status'=>'success','source'=>'wallet']);
            },3);
        });
        return $result instanceof CommerceOrder ? $result : $order->fresh(['items','transaction']);
    }

    public function verify(Transaction $transaction): CommerceOrder
    {
        $transaction->loadMissing('paymentGateway','transactionable');
        abort_unless($transaction->type==='payment' && $transaction->transactionable instanceof CommerceOrder,422);
        if($transaction->status==='completed') return $transaction->transactionable->fresh('items');
        abort_unless($transaction->paymentGateway,422,'This transaction does not use an external gateway.');
        $gateway=$this->gateways->gateway($transaction->paymentGateway->code);
        $result=$gateway->verifyPayment((string)$transaction->gateway_transaction_id);
        abort_unless(($result['status']??false)&&($result['paid']??false),422,'Payment has not been confirmed by the gateway.');
        $data=$result['data']??[];
        $paidMinor=isset($data['amount'])?(int)$data['amount']:(int)$transaction->amount_minor;
        abort_unless($paidMinor===(int)$transaction->amount_minor,422,'Gateway amount does not match the order total.');
        abort_unless(strtoupper((string)($data['currency']??$transaction->currency))===strtoupper($transaction->currency),422,'Gateway currency does not match the order.');
        return $this->complete($transaction,$data);
    }

    public function complete(Transaction $transaction,array $gatewayData=[]): CommerceOrder
    {
        return DB::transaction(function() use($transaction,$gatewayData){
            $transaction=Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $order=CommerceOrder::query()->whereKey($transaction->transactionable_id)->lockForUpdate()->firstOrFail();
            if($transaction->status==='completed') return $order->fresh(['items.entitlements','transaction']);
            $paymentSource=(string)($transaction->metadata['payment_source']??'gateway');
            if($paymentSource!=='wallet'){
                $amountMinor=$this->orderMinor($order);
                $this->ledger->post('marketplace-receipt:'.$order->uuid,'marketplace_payment',$order->currency,[
                    ['account_code'=>'payment_processor_receivable','direction'=>'debit','amount_minor'=>$amountMinor],
                    ['account_code'=>'marketplace_clearing','direction'=>'credit','amount_minor'=>$amountMinor],
                ],$order->buyer,['order_uuid'=>$order->uuid,'transaction_uuid'=>$transaction->uuid]);
            }
            $transaction->update(['status'=>'completed','gateway_status'=>$gatewayData['status']??'success','processed_at'=>now(),'metadata'=>array_merge($transaction->metadata??[],['gateway'=>$gatewayData])]);
            $order->update(['status'=>'completed','payment_status'=>'paid','paid_at'=>now(),'transaction_id'=>$transaction->id]);
            foreach($order->items()->with('purchasable')->get() as $item){
                CommerceEntitlement::updateOrCreate(
                    ['user_id'=>$order->buyer_id,'entitled_type'=>$item->purchasable_type,'entitled_id'=>$item->purchasable_id],
                    ['commerce_order_item_id'=>$item->id,'access_level'=>'full','status'=>'active','starts_at'=>now(),'revoked_at'=>null,'metadata'=>['order_uuid'=>$order->uuid]]
                );
                $this->allocate($item);
            }
            return $order->fresh(['items.entitlements','transaction']);
        },3);
    }

    public function initiateWalletFunding(User $user,string $amount,string $gatewayCode,string $idempotencyKey): array
    {
        $amountMinor=Money::toMinor($amount);
        $minimum=Money::toMinor((string)SettingService::get('wallet_minimum_funding_amount','500',$user->university_id));
        $currency=strtoupper((string)SettingService::get('currency','NGN',$user->university_id));
        if($amountMinor<$minimum) throw ValidationException::withMessages(['amount'=>'Minimum wallet funding amount is '.$currency.' '.Money::fromMinor($minimum).'.']);
        $gatewayModel=PaymentGateway::query()->where('code',$gatewayCode)->where('is_active',true)->firstOrFail();
        $gateway=$this->gateways->gateway($gatewayCode); abort_unless($gateway->isConfigured(),503,'The selected payment gateway is not configured.');
        $wallet=$this->wallets->account($user,$currency);
        abort_unless(strtoupper((string)$wallet->currency)===$currency,422,'Your wallet currency does not match the institution commercial currency.');

        return $this->idempotency->execute('wallet.fund',$idempotencyKey,['amount_minor'=>$amountMinor,'gateway'=>$gatewayCode],$user,function() use($user,$amountMinor,$gatewayModel,$gateway,$wallet,$idempotencyKey){
            $funding=WalletFundingRequest::create(['user_id'=>$user->id,'wallet_account_id'=>$wallet->id,'amount_minor'=>$amountMinor,'currency'=>$wallet->currency,'status'=>'pending','idempotency_key'=>$idempotencyKey]);
            $transaction=Transaction::create([
                'uuid'=>(string)Str::uuid(),'user_id'=>$user->id,'payment_gateway_id'=>$gatewayModel->id,'transactionable_type'=>$funding->getMorphClass(),'transactionable_id'=>$funding->id,
                'amount'=>Money::fromMinor($amountMinor),'amount_minor'=>$amountMinor,'currency'=>$wallet->currency,'type'=>'wallet_funding','status'=>'pending','gateway_transaction_id'=>'AFW-'.strtoupper(Str::random(18)),
                'metadata'=>['payment_source'=>'gateway','wallet_funding_uuid'=>$funding->uuid],
            ]);
            $funding->update(['transaction_id'=>$transaction->id,'gateway_reference'=>$transaction->gateway_transaction_id]);
            $result=$gateway->initializePayment(['email'=>$user->email,'amount_minor'=>$amountMinor,'amount'=>Money::fromMinor($amountMinor),'reference'=>$transaction->gateway_transaction_id,'currency'=>$wallet->currency,
                'callback_url'=>route('commerce.wallet.callback',$transaction),'user_id'=>$user->id,'type'=>'wallet_funding','metadata'=>['funding_uuid'=>$funding->uuid,'transaction_uuid'=>$transaction->uuid]]);
            if(!($result['status']??false)){ $transaction->update(['status'=>'failed','gateway_status'=>'initialization_failed']); $funding->update(['status'=>'failed']); throw ValidationException::withMessages(['payment'=>$result['message']??'Wallet funding could not be started.']); }
            $transaction->update(['status'=>'processing','gateway_status'=>'initialized']);
            return ['funding_uuid'=>$funding->uuid,'transaction_uuid'=>$transaction->uuid,'authorization_url'=>$result['authorization_url']??null];
        });
    }

    public function verifyWalletFunding(Transaction $transaction): WalletFundingRequest
    {
        $transaction->loadMissing('paymentGateway','transactionable');
        abort_unless($transaction->type==='wallet_funding' && $transaction->transactionable instanceof WalletFundingRequest,422);
        $funding=$transaction->transactionable;
        if($funding->status==='completed') return $funding->fresh();
        abort_unless($transaction->paymentGateway,422,'This wallet funding transaction does not have a payment gateway.');
        $gateway=$this->gateways->gateway($transaction->paymentGateway->code);
        $result=$gateway->verifyPayment((string)$transaction->gateway_transaction_id);
        abort_unless(($result['status']??false)&&($result['paid']??false),422,'Wallet funding has not been confirmed by the gateway.');
        $data=$result['data']??[];
        $paidMinor=isset($data['amount'])?(int)$data['amount']:(int)$transaction->amount_minor;
        abort_unless($paidMinor===(int)$funding->amount_minor,422,'Gateway amount does not match the wallet funding request.');
        abort_unless(strtoupper((string)($data['currency']??$funding->currency))===strtoupper($funding->currency),422,'Gateway currency does not match the wallet funding request.');

        return DB::transaction(function() use($transaction,$funding,$data){
            $locked=WalletFundingRequest::query()->whereKey($funding->id)->lockForUpdate()->firstOrFail();
            if($locked->status==='completed') return $locked;
            $tx=Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $tx->update(['status'=>'completed','gateway_status'=>$data['status']??'success','processed_at'=>now(),'metadata'=>array_merge($tx->metadata??[],['gateway'=>$data])]);
            $locked->update(['status'=>'completed','verified_at'=>now()]);
            $amountMinor=(int)$locked->amount_minor;
            $this->ledger->post('wallet-funding-receipt:'.$locked->uuid,'wallet_funding_receipt',$locked->currency,[
                ['account_code'=>'payment_processor_receivable','direction'=>'debit','amount_minor'=>$amountMinor],
                ['account_code'=>'payment_clearing','direction'=>'credit','amount_minor'=>$amountMinor],
            ],$locked->user,['funding_uuid'=>$locked->uuid,'gateway_reference'=>$locked->gateway_reference]);
            $this->wallets->creditSpending($locked->user,$amountMinor,'wallet_funding',$locked,$tx,'Wallet funding confirmed.',['gateway_reference'=>$locked->gateway_reference]);
            return $locked->fresh();
        },3);
    }

    public function requestRefund(CommerceOrder $order, User $requester, string $amount, string $reason, string $idempotencyKey): CommerceRefund
    {
        abort_unless($order->buyer_id === $requester->id || $requester->isAdmin(), 403);
        if ($requester->isAdmin() && ! $requester->isSuperAdmin()) {
            abort_unless($order->university_id === $requester->university_id, 403);
        }
        abort_unless(in_array($order->payment_status, ['paid', 'partially_refunded'], true), 422, 'Only paid orders can be refunded.');

        $amountMinor = Money::toMinor($amount);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
        }

        // Requested and processing refunds reserve their amount so concurrent requests
        // cannot over-refund the order while an administrator/provider operation is pending.
        $alreadyReserved = (int) $order->refunds()
            ->whereIn('status', ['requested', 'processing', 'completed'])
            ->sum('amount_minor');
        abort_unless($alreadyReserved + $amountMinor <= $this->orderMinor($order), 422, 'Refund amount exceeds the unrefunded order balance.');

        $result = $this->idempotency->execute(
            'commerce.refund_request',
            $idempotencyKey,
            ['order_uuid' => $order->uuid, 'amount_minor' => $amountMinor],
            $requester,
            fn () => $order->refunds()->create([
                'requested_by' => $requester->id,
                'amount' => Money::fromMinor($amountMinor),
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'status' => 'requested',
            ])
        );

        if ($result instanceof CommerceRefund) return $result;
        $id = (int) ($result['id'] ?? 0);
        return CommerceRefund::query()->whereKey($id)->where('commerce_order_id', $order->id)->firstOrFail();
    }

    /**
     * Approve/reject a refund using a resumable state machine.
     *
     * External refund POSTs are intentionally performed outside the database
     * transaction. Once the provider call begins, an uncertain transport outcome
     * is never replayed automatically; the row is marked for reconciliation.
     */
    public function processRefund(CommerceRefund $refund, User $actor, bool $approve, ?string $note = null): CommerceRefund
    {
        abort_unless($actor->isAdmin(), 403);

        $preparation = DB::transaction(function () use ($refund, $actor, $approve, $note): array {
            $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $order = $locked->order()->firstOrFail();
            if (! $actor->isSuperAdmin()) abort_unless($order->university_id === $actor->university_id, 403);

            if ($locked->status === 'completed') return ['action' => 'completed', 'refund_id' => $locked->id];
            abort_if($locked->status === 'rejected', 422, 'Refund has already been rejected.');

            if (! $approve) {
                abort_unless($locked->status === 'requested', 422, 'A refund cannot be rejected after provider processing has started.');
                $locked->update([
                    'status' => 'rejected',
                    'processed_by' => $actor->id,
                    'decision_note' => $note,
                    'processed_at' => now(),
                ]);
                return ['action' => 'rejected', 'refund_id' => $locked->id];
            }

            if ($locked->status === 'requested') {
                $locked->update([
                    'status' => 'processing',
                    'processed_by' => $actor->id,
                    'decision_note' => $note,
                    'processing_started_at' => now(),
                    'reconciliation_required' => false,
                ]);
                return ['action' => 'call_provider', 'refund_id' => $locked->id];
            }

            abort_unless($locked->status === 'processing', 422, 'Refund is not in a processable state.');

            // A confirmed provider response means the external side already succeeded;
            // resume only the local ledger/order finalization.
            if ($locked->provider_confirmed_at || $locked->gateway_refund_id || $locked->provider_status === 'internal_confirmed') {
                return ['action' => 'finalize', 'refund_id' => $locked->id];
            }

            if ($locked->reconciliation_required) {
                return ['action' => 'reconcile', 'refund_id' => $locked->id];
            }

            // A second request arriving while the first process is still active must not
            // issue another provider POST. Only mark stale operations for reconciliation.
            if ($locked->processing_started_at && $locked->processing_started_at->gt(now()->subMinutes(2))) {
                return ['action' => 'in_progress', 'refund_id' => $locked->id];
            }

            $locked->update([
                'reconciliation_required' => true,
                'provider_status' => 'outcome_unknown',
            ]);
            return ['action' => 'reconcile', 'refund_id' => $locked->id];
        }, 3);

        if ($preparation['action'] === 'completed' || $preparation['action'] === 'rejected') {
            return CommerceRefund::query()->findOrFail($preparation['refund_id']);
        }
        if ($preparation['action'] === 'in_progress') {
            throw ValidationException::withMessages(['refund' => 'This refund is already being processed. Refresh shortly before taking another action.']);
        }
        if ($preparation['action'] === 'reconcile') {
            throw ValidationException::withMessages(['refund' => 'This refund has an uncertain provider outcome and requires reconciliation before any retry. No second provider refund was sent.']);
        }

        $refund = CommerceRefund::query()->with('order.transaction.paymentGateway')->findOrFail($preparation['refund_id']);
        $paymentSource = (string) ($refund->order?->transaction?->metadata['payment_source'] ?? 'gateway');

        if ($preparation['action'] === 'call_provider') {
            if ($paymentSource === 'wallet') {
                DB::transaction(function () use ($refund): void {
                    $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                    if ($locked->provider_confirmed_at) return;
                    $locked->update([
                        'gateway_refund_id' => 'internal-'.$locked->uuid,
                        'provider_status' => 'internal_confirmed',
                        'provider_payload' => ['source' => 'wallet'],
                        'provider_confirmed_at' => now(),
                        'reconciliation_required' => false,
                    ]);
                }, 3);
            } else {
                $transaction = $refund->order?->transaction;
                abort_unless($transaction?->paymentGateway, 422, 'The original gateway transaction is unavailable.');
                $gateway = $this->gateways->gateway($transaction->paymentGateway->code);
                try {
                    $result = $gateway->refund((string) $transaction->gateway_transaction_id, (int) $refund->amount_minor);
                } catch (\Throwable $exception) {
                    DB::transaction(function () use ($refund, $exception): void {
                        $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status === 'completed') return;
                        $locked->update([
                            'status' => 'processing',
                            'provider_status' => 'outcome_unknown',
                            'provider_payload' => ['exception' => $exception::class],
                            'reconciliation_required' => true,
                        ]);
                    }, 3);

                    throw ValidationException::withMessages([
                        'refund' => 'The refund provider connection ended without a conclusive result. The refund is locked for reconciliation and was not retried.',
                    ]);
                }

                if (! ($result['status'] ?? false)) {
                    $unknown = (bool) ($result['outcome_unknown'] ?? false);
                    DB::transaction(function () use ($refund, $result, $unknown): void {
                        $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status === 'completed') return;
                        $locked->update([
                            'status' => $unknown ? 'processing' : 'requested',
                            'provider_status' => $unknown ? 'outcome_unknown' : 'rejected',
                            'provider_payload' => $result['data'] ?? ['code' => $result['code'] ?? null],
                            'reconciliation_required' => $unknown,
                            'processing_started_at' => $unknown ? $locked->processing_started_at : null,
                        ]);
                    }, 3);

                    throw ValidationException::withMessages([
                        'refund' => $result['message'] ?? 'The refund provider did not accept the refund.',
                    ]);
                }

                $providerData = (array) ($result['data'] ?? []);
                $providerRefundId = (string) ($providerData['id'] ?? $providerData['reference'] ?? $providerData['refund_reference'] ?? '');
                if ($providerRefundId === '') {
                    DB::transaction(function () use ($refund, $providerData): void {
                        $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status === 'completed') return;
                        $locked->update([
                            'status' => 'processing',
                            'provider_status' => 'accepted_missing_reference',
                            'provider_payload' => $providerData,
                            'reconciliation_required' => true,
                        ]);
                    }, 3);
                    throw ValidationException::withMessages([
                        'refund' => 'The provider accepted the refund but did not return a reference. The refund is locked for manual reconciliation and will not be sent again.',
                    ]);
                }

                DB::transaction(function () use ($refund, $providerData, $providerRefundId): void {
                    $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                    if ($locked->provider_confirmed_at) return;
                    if(CommerceRefund::query()->where('gateway_refund_id',$providerRefundId)->where('id','!=',$locked->id)->exists()){
                        $locked->update(['provider_status'=>'duplicate_provider_reference','provider_payload'=>$providerData,'reconciliation_required'=>true]);
                        throw ValidationException::withMessages(['refund'=>'The provider returned a refund reference already linked to another refund. Reconciliation is required before local settlement.']);
                    }
                    $locked->update([
                        'gateway_refund_id' => $providerRefundId,
                        'provider_status' => (string) ($providerData['status'] ?? 'accepted'),
                        'provider_payload' => $providerData,
                        'provider_confirmed_at' => now(),
                        'reconciliation_required' => false,
                    ]);
                }, 3);
            }
        }

        return $this->finalizeRefundLocally($refund->fresh(), $actor, $note);
    }

    /**
     * Finalize only local state after provider confirmation. This transaction is
     * safe to retry because every ledger reference is deterministic and the refund
     * status is locked before any wallet/order mutation.
     */
    private function finalizeRefundLocally(CommerceRefund $refund, User $actor, ?string $note): CommerceRefund
    {
        return DB::transaction(function () use ($refund, $actor, $note): CommerceRefund {
            $refund = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'completed') return $refund->fresh();
            abort_unless($refund->status === 'processing', 422, 'Refund is not ready for finalization.');
            abort_unless($refund->provider_confirmed_at || $refund->provider_status === 'internal_confirmed', 409, 'Provider refund confirmation is required before local finalization.');

            $order = $refund->order()->with([
                'transaction.paymentGateway',
                'buyer',
                'items.entitlements',
                'items.revenueAllocations.beneficiaryUser',
            ])->lockForUpdate()->firstOrFail();
            if (! $actor->isSuperAdmin()) abort_unless($order->university_id === $actor->university_id, 403);

            $refundMinor = (int) $refund->amount_minor;
            $orderMinor = $this->orderMinor($order);
            $completedBefore = (int) $order->refunds()->where('status', 'completed')->where('id', '!=', $refund->id)->sum('amount_minor');
            $completedAfter = min($orderMinor, $completedBefore + $refundMinor);
            $full = $completedAfter >= $orderMinor;

            if (($order->transaction?->metadata['payment_source'] ?? null) === 'wallet') {
                $this->wallets->creditSpending(
                    $order->buyer,
                    $refundMinor,
                    'marketplace_refund',
                    $refund,
                    $order->transaction,
                    'Wallet purchase refund.',
                    ['order_uuid' => $order->uuid]
                );
            }

            $targets = $this->allocationReversalTargets($order, $completedAfter, $orderMinor);
            foreach ($order->items as $item) {
                if ($full) $item->entitlements()->update(['status' => 'revoked', 'revoked_at' => now()]);

                foreach ($item->revenueAllocations as $allocation) {
                    $allocationMinor = (int) $allocation->amount_minor;
                    if ($allocationMinor <= 0) continue;

                    $meta = $allocation->metadata ?? [];
                    $alreadyReversed = (int) ($meta['reversed_amount_minor'] ?? 0);
                    $target = (int) ($targets[$allocation->id] ?? $alreadyReversed);
                    $reversal = max(0, min($allocationMinor, $target) - $alreadyReversed);
                    if ($reversal <= 0) continue;

                    if ($allocation->beneficiary_user_id) {
                        $earningBucket = (string) ($meta['earning_bucket'] ?? ($allocation->status === 'pending' ? 'pending' : 'available'));
                        if ($earningBucket === 'pending') {
                            $this->wallets->debitPendingEarnings($allocation->beneficiaryUser, $reversal, 'refund_reversal', $refund, 'Pending sale earnings reversed after refund.', ['allocation_id' => $allocation->id], true);
                        } else {
                            $this->wallets->debitAvailableEarnings($allocation->beneficiaryUser, $reversal, 'refund_reversal', $refund, 'Sale earnings reversed after refund.', ['allocation_id' => $allocation->id], true);
                        }
                    } elseif ($allocation->allocation_type === 'platform') {
                        $this->ledger->post('allocation-refund:'.$allocation->id.':'.$refund->uuid, 'marketplace_refund_allocation', $order->currency, [
                            ['account_code' => 'platform_revenue', 'direction' => 'debit', 'amount_minor' => $reversal],
                            ['account_code' => 'refund_clearing', 'direction' => 'credit', 'amount_minor' => $reversal],
                        ], $actor, ['allocation_id' => $allocation->id, 'refund_uuid' => $refund->uuid]);
                    } elseif ($allocation->allocation_type === 'institution') {
                        $this->ledger->post('allocation-refund:'.$allocation->id.':'.$refund->uuid, 'marketplace_refund_allocation', $order->currency, [
                            ['account_code' => 'institution_payable:'.($allocation->beneficiary_university_id?:'unassigned'), 'direction' => 'debit', 'amount_minor' => $reversal],
                            ['account_code' => 'refund_clearing', 'direction' => 'credit', 'amount_minor' => $reversal],
                        ], $actor, ['allocation_id' => $allocation->id, 'refund_uuid' => $refund->uuid, 'university_id' => $allocation->beneficiary_university_id]);
                    }

                    $totalReversed = min($allocationMinor, $alreadyReversed + $reversal);
                    $allocation->update([
                        'status' => $totalReversed >= $allocationMinor ? 'reversed' : 'partially_reversed',
                        'metadata' => array_merge($meta, [
                            'reversed_amount_minor' => $totalReversed,
                            'reversed_amount' => Money::fromMinor($totalReversed),
                        ]),
                    ]);
                }
            }

            if (($order->transaction?->metadata['payment_source'] ?? null) !== 'wallet') {
                $this->ledger->post('refund-payout:'.$refund->uuid, 'marketplace_refund_payout', $order->currency, [
                    ['account_code' => 'refund_clearing', 'direction' => 'debit', 'amount_minor' => $refundMinor],
                    ['account_code' => 'payment_processor_refund_payable', 'direction' => 'credit', 'amount_minor' => $refundMinor],
                ], $actor, [
                    'refund_uuid' => $refund->uuid,
                    'order_uuid' => $order->uuid,
                    'gateway_refund_id' => $refund->gateway_refund_id,
                ]);
            }

            $order->update([
                'status' => $full ? 'refunded' : 'partially_refunded',
                'payment_status' => $full ? 'refunded' : 'partially_refunded',
            ]);
            if ($full) $order->transaction?->update(['status' => 'refunded']);

            $refund->update([
                'status' => 'completed',
                'processed_by' => $actor->id,
                'decision_note' => $note ?? $refund->decision_note,
                'processed_at' => now(),
                'reconciliation_required' => false,
            ]);

            return $refund->fresh();
        }, 3);
    }

    /** @return array<int,int> allocation id => cumulative reversed minor units */
    private function allocationReversalTargets(CommerceOrder $order, int $cumulativeRefundMinor, int $orderMinor): array
    {
        $allocations = $order->items->flatMap(fn (CommerceOrderItem $item) => $item->revenueAllocations)->values();
        if ($allocations->isEmpty() || $cumulativeRefundMinor <= 0 || $orderMinor <= 0) return [];

        $targets = [];
        $remainders = [];
        $allocated = 0;
        foreach ($allocations as $allocation) {
            $amount = max(0, (int) $allocation->amount_minor);
            $numerator = $amount * min($cumulativeRefundMinor, $orderMinor);
            $base = min($amount, intdiv($numerator, $orderMinor));
            $targets[$allocation->id] = $base;
            $remainders[$allocation->id] = $numerator % $orderMinor;
            $allocated += $base;
        }

        $desired = min($cumulativeRefundMinor, (int) $allocations->sum(fn ($allocation) => (int) $allocation->amount_minor));
        $remaining = max(0, $desired - $allocated);
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $allocationId) {
            if ($remaining <= 0) break;
            $allocation = $allocations->firstWhere('id', $allocationId);
            if (! $allocation || $targets[$allocationId] >= (int) $allocation->amount_minor) continue;
            $targets[$allocationId]++;
            $remaining--;
        }

        return $targets;
    }

    /**
     * Resolve a refund whose external-provider outcome could not be determined.
     * This action requires an administrator to first verify the provider dashboard.
     */
    public function reconcileRefund(CommerceRefund $refund, User $actor, string $outcome, ?string $providerReference = null, ?string $note = null): CommerceRefund
    {
        abort_unless($actor->isAdmin(), 403);
        abort_unless(in_array($outcome, ['confirmed', 'not_refunded'], true), 422, 'Invalid reconciliation outcome.');

        $result = DB::transaction(function () use ($refund, $actor, $outcome, $providerReference, $note): CommerceRefund {
            $locked = CommerceRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $order = $locked->order()->firstOrFail();
            if (! $actor->isSuperAdmin()) abort_unless($order->university_id === $actor->university_id, 403);
            abort_unless($locked->status === 'processing' && $locked->reconciliation_required, 422, 'This refund is not awaiting reconciliation.');

            if ($outcome === 'confirmed') {
                $providerReference = trim((string) $providerReference);
                if($providerReference!=='' && CommerceRefund::query()->where('gateway_refund_id',$providerReference)->where('id','!=',$locked->id)->exists()){
                    throw ValidationException::withMessages(['provider_reference'=>'This provider refund reference is already linked to another refund.']);
                }
                if (($order->transaction?->metadata['payment_source'] ?? null) !== 'wallet' && $providerReference === '') {
                    throw ValidationException::withMessages(['provider_reference' => 'A provider refund reference is required when confirming an external refund.']);
                }
                $locked->update([
                    'gateway_refund_id' => $providerReference !== '' ? $providerReference : 'reconciled-'.$locked->uuid,
                    'provider_status' => 'reconciled_confirmed',
                    'provider_payload' => array_merge($locked->provider_payload ?? [], [
                        'reconciled_by' => $actor->id,
                        'reconciled_at' => now()->toIso8601String(),
                        'reconciliation_note' => $note,
                    ]),
                    'provider_confirmed_at' => now(),
                    'reconciliation_required' => false,
                    'decision_note' => $note ?? $locked->decision_note,
                ]);
            } else {
                $locked->update([
                    'status' => 'requested',
                    'gateway_refund_id' => null,
                    'provider_status' => 'reconciled_not_refunded',
                    'provider_payload' => array_merge($locked->provider_payload ?? [], [
                        'reconciled_by' => $actor->id,
                        'reconciled_at' => now()->toIso8601String(),
                        'reconciliation_note' => $note,
                    ]),
                    'processing_started_at' => null,
                    'provider_confirmed_at' => null,
                    'reconciliation_required' => false,
                    'decision_note' => $note ?? $locked->decision_note,
                ]);
            }

            return $locked->fresh();
        }, 3);

        if ($outcome === 'confirmed') {
            return $this->processRefund($result, $actor, true, $note);
        }

        return $result;
    }

    public function requestWithdrawal(User $user,int $payoutAccountId,string $amount,string $idempotencyKey): WithdrawalRequest
    {
        $amountMinor=Money::toMinor($amount); $wallet=$this->wallets->account($user);
        $payout=$user->payoutAccounts()->whereKey($payoutAccountId)->where('is_verified',true)->firstOrFail();
        abort_unless(strtoupper((string)$payout->currency)===strtoupper((string)$wallet->currency),422,'Payout-account currency must match your earnings wallet currency.');
        $minimum=Money::toMinor((string)SettingService::get('minimum_withdrawal_amount','1000',$user->university_id));
        abort_unless($amountMinor>=$minimum,422,'Amount is below the configured withdrawal minimum.');
        $feeBp=Money::clampBasisPoints(Money::toMinor((string)SettingService::get('withdrawal_fee_percentage','0',$user->university_id))); $feeMinor=Money::percentage($amountMinor,$feeBp);
        $result=$this->idempotency->execute('wallet.withdrawal',$idempotencyKey,['payout_account_id'=>$payoutAccountId,'amount_minor'=>$amountMinor],$user,function() use($wallet,$payout,$amountMinor,$feeMinor,$user){
            return DB::transaction(function() use($wallet,$payout,$amountMinor,$feeMinor,$user){
                $this->wallets->debitAvailableEarnings($user,$amountMinor,'withdrawal_hold',$payout,'Funds reserved for withdrawal.');
                return WithdrawalRequest::create(['wallet_account_id'=>$wallet->id,'payout_account_id'=>$payout->id,'amount'=>Money::fromMinor($amountMinor),'amount_minor'=>$amountMinor,'fee'=>Money::fromMinor($feeMinor),'fee_minor'=>$feeMinor,'status'=>'pending']);
            },3);
        });
        if ($result instanceof WithdrawalRequest) return $result;
        $id = (int) ($result['id'] ?? 0);
        return WithdrawalRequest::query()->whereKey($id)->where('wallet_account_id', $wallet->id)->firstOrFail();
    }

    public function processWithdrawal(WithdrawalRequest $withdrawal,User $actor,string $decision,?string $reference=null,?string $note=null): WithdrawalRequest
    {
        abort_unless($actor->isAdmin(),403);
        return DB::transaction(function() use($withdrawal,$actor,$decision,$reference,$note){
            $withdrawal=WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->with('wallet.user')->firstOrFail();
            if(!$actor->isSuperAdmin()) abort_unless($withdrawal->wallet->user->university_id===$actor->university_id,403);
            abort_unless($withdrawal->status==='pending',422,'Withdrawal already processed.');
            if($decision==='reject'){
                $this->wallets->creditAvailableEarnings($withdrawal->wallet->user,(int)$withdrawal->amount_minor,'withdrawal_release',$withdrawal,'Rejected withdrawal funds returned.');
                $withdrawal->update(['status'=>'rejected','processed_by'=>$actor->id,'note'=>$note,'processed_at'=>now()]);
            } elseif($decision==='approve'){
                $reference=trim((string)$reference); abort_unless($reference!=='',422,'A provider or bank reference is required.');
                abort_if(WithdrawalRequest::query()->where('provider_reference',$reference)->where('id','!=',$withdrawal->id)->exists(),422,'This provider or bank payout reference has already been used.');
                $gross=(int)$withdrawal->amount_minor; $fee=min($gross,max(0,(int)$withdrawal->fee_minor)); $net=$gross-$fee;
                $postings=[['wallet_account_id'=>$withdrawal->wallet_account_id,'account_code'=>'payout_clearing','direction'=>'debit','amount_minor'=>$gross]];
                if($net>0)$postings[]=['account_code'=>'payout_cash_out','direction'=>'credit','amount_minor'=>$net];
                if($fee>0)$postings[]=['account_code'=>'platform_withdrawal_fee_revenue','direction'=>'credit','amount_minor'=>$fee];
                $this->ledger->post('withdrawal-paid:'.$withdrawal->uuid,'withdrawal_payout',$withdrawal->wallet->currency,$postings,$withdrawal->wallet->user,['withdrawal_uuid'=>$withdrawal->uuid,'provider_reference'=>$reference]);
                $withdrawal->update(['status'=>'paid','provider_reference'=>$reference,'processed_by'=>$actor->id,'note'=>$note,'processed_at'=>now()]);
            } else throw ValidationException::withMessages(['decision'=>'Invalid withdrawal decision.']);
            return $withdrawal->fresh();
        },3);
    }

    public function releaseMatureCreatorEarnings(int $limit=200): int
    {
        $holdDays=max(0,(int)SettingService::get('creator_earnings_hold_days',3)); $released=0;
        CommerceRevenueAllocation::query()->where('allocation_type','creator')->whereIn('status',['pending','partially_reversed'])->where('created_at','<=',now()->subDays($holdDays))->orderBy('id')->limit($limit)->get()->each(function(CommerceRevenueAllocation $allocation) use(&$released){
            DB::transaction(function() use($allocation,&$released){
                $locked=CommerceRevenueAllocation::query()->whereKey($allocation->id)->lockForUpdate()->with('beneficiaryUser')->firstOrFail();
                $meta=$locked->metadata??[];
                if(!in_array($locked->status,['pending','partially_reversed'],true)||!$locked->beneficiaryUser||($meta['earning_bucket']??'pending')!=='pending')return;
                $reversed=(int)($meta['reversed_amount_minor']??0); $remaining=max(0,(int)$locked->amount_minor-$reversed);
                if($remaining<=0){$locked->update(['status'=>'reversed']);return;}
                $this->wallets->releasePending($locked->beneficiaryUser,$remaining,$locked,'Creator earnings released after settlement hold.');
                $locked->update(['status'=>$reversed>0?'partially_reversed':'released','released_at'=>now(),'metadata'=>array_merge($meta,['earning_bucket'=>'available'])]); $released++;
            },3);
        });
        return $released;
    }

    private function allocate(CommerceOrderItem $item): void
    {
        if($item->revenueAllocations()->exists())return;
        $platformBp=Money::clampBasisPoints(Money::toMinor((string)SettingService::get('knowledge_platform_commission_percentage','15',$item->order->university_id)));
        $institutionBp=min(10_000-$platformBp,Money::clampBasisPoints(Money::toMinor((string)SettingService::get('knowledge_institution_revenue_percentage','0',$item->order->university_id))));
        $creatorBp=10_000-$platformBp-$institutionBp; $total=(int)$item->total_price_minor;
        $platform=Money::percentage($total,$platformBp); $institution=Money::percentage($total,$institutionBp); $creator=max(0,$total-$platform-$institution);
        if(!$item->seller_id){ $platform+=$creator; $creator=0; $platformBp=10_000-$institutionBp; $creatorBp=0; }
        $rows=[
            ['allocation_type'=>'platform','percentage'=>Money::fromMinor($platformBp,2),'amount_minor'=>$platform,'amount'=>Money::fromMinor($platform),'status'=>'settled','released_at'=>now()],
            ['allocation_type'=>'institution','beneficiary_university_id'=>$item->order->university_id,'percentage'=>Money::fromMinor($institutionBp,2),'amount_minor'=>$institution,'amount'=>Money::fromMinor($institution),'status'=>'settled','released_at'=>now()],
            ['allocation_type'=>'creator','beneficiary_user_id'=>$item->seller_id,'percentage'=>Money::fromMinor($creatorBp,2),'amount_minor'=>$creator,'amount'=>Money::fromMinor($creator),'status'=>'pending','released_at'=>null],
        ];
        foreach($rows as $row){
            if((int)$row['amount_minor']<=0)continue;
            $meta=['settlement_hold_days'=>(int)SettingService::get('creator_earnings_hold_days',3)];
            if(($row['allocation_type']??null)==='creator')$meta['earning_bucket']='pending';
            $allocation=$item->revenueAllocations()->create($row+['metadata'=>$meta]);
            $amount=(int)$allocation->amount_minor;
            if($allocation->beneficiary_user_id){
                $this->wallets->creditPendingEarnings($allocation->beneficiaryUser,$amount,'sale_earning',$allocation,'Knowledge Hub sale earnings pending settlement.');
            } elseif($allocation->allocation_type==='platform') {
                $this->ledger->post('allocation:'.$allocation->id,'marketplace_platform_revenue',$item->order->currency,[
                    ['account_code'=>'marketplace_clearing','direction'=>'debit','amount_minor'=>$amount],
                    ['account_code'=>'platform_revenue','direction'=>'credit','amount_minor'=>$amount],
                ],null,['allocation_id'=>$allocation->id,'order_uuid'=>$item->order->uuid]);
            } elseif($allocation->allocation_type==='institution') {
                $this->ledger->post('allocation:'.$allocation->id,'marketplace_institution_payable',$item->order->currency,[
                    ['account_code'=>'marketplace_clearing','direction'=>'debit','amount_minor'=>$amount],
                    ['account_code'=>'institution_payable:'.($allocation->beneficiary_university_id?:'unassigned'),'direction'=>'credit','amount_minor'=>$amount],
                ],null,['allocation_id'=>$allocation->id,'order_uuid'=>$item->order->uuid,'university_id'=>$allocation->beneficiary_university_id]);
            }
        }
    }

    private function orderMinor(CommerceOrder $order): int
    {
        return $order->total_amount_minor!==null?(int)$order->total_amount_minor:Money::toMinor((string)$order->total_amount);
    }

    private function assertPurchasable(Model $purchasable,User $buyer): void
    {
        $supported=$purchasable instanceof KnowledgePublication||$purchasable instanceof LearningPath; abort_unless($supported,422,'Unsupported marketplace item.');
        if($purchasable instanceof LearningPath && $purchasable->visibility!=='public'){
            abort_unless($buyer->isSuperAdmin() || $purchasable->university_id===$buyer->university_id,403);
        }
        if($purchasable instanceof KnowledgePublication && $purchasable->visibility!=='public'){
            abort_unless($buyer->isSuperAdmin() || $purchasable->university_id===$buyer->university_id,403);
        }
        $published=$purchasable instanceof KnowledgePublication?$purchasable->isPublished():$purchasable->status==='published';
        abort_unless($published&&$purchasable->access_type==='premium',422,'This item is not available for purchase.');
        abort_if($purchasable->creator_id===$buyer->id,422,'Creators already have access to their own item.'); abort_if($buyer->hasEntitlement($purchasable),422,'You already own this item.');
    }
}
