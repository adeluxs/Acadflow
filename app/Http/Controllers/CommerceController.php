<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\KnowledgePublication;
use App\Models\LearningPath;
use App\Models\PaymentGateway;
use App\Models\PayoutAccount;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Services\Commerce\CommerceService;
use App\Services\Commerce\WalletService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CommerceController extends Controller
{
    public function subscriptionRetired(): RedirectResponse
    {
        return redirect()->route('commerce.wallet')
            ->with('info', 'Subscriptions have been retired. AcadSyntra now uses wallet, usage and entitlement based access.');
    }

    public function orders(Request $request): View
    {
        $user=$request->user();
        $orders=CommerceOrder::query()->with(['items.purchasable','transaction.paymentGateway','refunds'])
            ->when(!$user->isAdmin(),fn($q)=>$q->where('buyer_id',$user->id))
            ->when($user->isAdmin()&&!$user->isSuperAdmin(),fn($q)=>$q->where('university_id',$user->university_id))->latest()->paginate(25);
        return view('commerce.orders',compact('orders'));
    }

    public function purchase(Request $request,KnowledgePublication $publication,CommerceService $commerce): RedirectResponse
    {
        $this->authorize('view',$publication);
        return $this->beginPurchase($request,$publication,$commerce);
    }

    public function purchaseLearningPath(Request $request,LearningPath $path,CommerceService $commerce): RedirectResponse
    {
        return $this->beginPurchase($request,$path,$commerce);
    }

    private function beginPurchase(Request $request,\Illuminate\Database\Eloquent\Model $purchasable,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['payment_method'=>['nullable','in:gateway,wallet'],'gateway'=>['nullable','required_unless:payment_method,wallet','string','exists:payment_gateways,code'],'idempotency_key'=>['required','string','max:120']]);
        $order=$commerce->createOrder($request->user(),$purchasable,['email'=>$request->user()->email],$data['idempotency_key']);
        if(($data['payment_method']??'gateway')==='wallet'){
            $commerce->purchaseWithWallet($order,$request->user(),$data['idempotency_key']);
            return redirect()->route('commerce.orders')->with('success','Purchase completed from your spending balance.');
        }
        $result=$commerce->initialize($order,(string)$data['gateway']);
        return redirect()->away((string)$result['authorization_url']);
    }

    public function callback(Request $request,Transaction $transaction,CommerceService $commerce): RedirectResponse
    {
        $this->assertTransactionAccess($request,$transaction);
        try { $order=$commerce->verify($transaction); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [422, 503], true)) throw $exception;
            return redirect()->route('commerce.orders')->with('error', 'Payment is not yet confirmed. Check its status again before starting another payment.');
        }
        return redirect()->route('commerce.orders')->with('success','Payment confirmed. Your access entitlement is active for order '.$order->order_number.'.');
    }

    public function walletFundingCallback(Request $request,Transaction $transaction,CommerceService $commerce): RedirectResponse
    {
        $this->assertTransactionAccess($request,$transaction);
        try { $commerce->verifyWalletFunding($transaction); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [422, 503], true)) throw $exception;
            return redirect()->route('commerce.wallet')->with('error', 'Wallet funding is not yet confirmed. Check its status again before starting another payment.');
        }
        return redirect()->route('commerce.wallet')->with('success','Wallet funding confirmed and your spending balance has been updated.');
    }

    public function webhook(Request $request,string $gateway,PaymentGatewayManager $gateways,CommerceService $commerce)
    {
        $model = PaymentGateway::query()->where('code', $gateway)->firstOrFail();
        $adapter = $gateways->gateway($gateway);
        abort_unless($adapter->validateWebhook($request->getContent(), $request->header('X-Paystack-Signature')), 403, 'Invalid webhook signature.');
        $payload = $request->json()->all();
        $event = $payload['event'] ?? '';
        try {
            if (is_string($event) && str_starts_with($event, 'refund.')) {
                return response()->json(['received' => true, 'matched' => $commerce->handleRefundWebhook($model, $payload)]);
            }
            if ($event !== 'charge.success') return response()->json(['received' => true, 'ignored' => true]);
            $reference = data_get($payload, 'data.reference');
            if (! is_string($reference) || $reference === '') return response()->json(['received' => true, 'matched' => false]);
            $transaction = Transaction::query()->where('payment_gateway_id', $model->id)->where('gateway_transaction_id', $reference)->first();
            if ($transaction) {
                if ($transaction->type === 'wallet_funding') $commerce->verifyWalletFunding($transaction);
                elseif ($transaction->transactionable_type === CommerceOrder::class) $commerce->verify($transaction);
                else return app(SubscriptionController::class)->webhook($request, $gateway);
            } else {
                $payment = \App\Models\Payment::query()->where('reference', $reference)->first();
                $metadata = $payment ? (json_decode((string) $payment->notes, true) ?: []) : [];
                if (! $payment || ($metadata['gateway'] ?? null) !== $gateway) return response()->json(['received' => true, 'matched' => false]);
                app(\App\Services\PaymentService::class)->verifyGatewayPayment($payment);
            }
        } catch (\Throwable $exception) {
            Log::warning('Payment webhook settlement deferred', ['gateway_id' => $model->id, 'exception_class' => $exception::class]);
            // No durable inbox exists here: request provider redelivery until settlement succeeds.
            return response()->json(['received' => true, 'verified' => false], 503);
        }
        return response()->json(['received' => true, 'verified' => true]);
    }

    public function wallet(Request $request,WalletService $wallets): View
    {
        $wallet=$wallets->account($request->user()); $wallet->load(['entries'=>fn($q)=>$q->latest()->limit(100),'withdrawals.payoutAccount']); $accounts=$request->user()->payoutAccounts()->latest()->get();
        $allocations=\App\Models\CommerceRevenueAllocation::query()->with('orderItem.order')->where('beneficiary_user_id',$request->user()->id)->latest()->paginate(25);
        $gateways=app(PaymentGatewayManager::class)->getCheckoutGateways();
        $walletRules=[
            'minimum_funding'=>Money::fromMinor(Money::toMinor((string)SettingService::getGlobal('wallet_minimum_funding_amount','500'))),
            'minimum_withdrawal'=>Money::fromMinor(Money::toMinor((string)SettingService::getGlobal('minimum_withdrawal_amount','1000'))),
            'withdrawal_fee'=>(string)SettingService::getGlobal('withdrawal_fee_percentage','0'),
        ];
        $pendingFunding = \App\Models\WalletFundingRequest::query()->with('transaction')->where('user_id', $request->user()->id)->where('status', 'pending')->latest()->limit(20)->get();
        return view('commerce.wallet',compact('wallet','accounts','allocations','gateways','walletRules','pendingFunding'));
    }

    public function fundWallet(Request $request,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'gateway'=>['required','string','exists:payment_gateways,code'],'idempotency_key'=>['required','string','max:120']]);
        $result=$commerce->initiateWalletFunding($request->user(),$data['amount'],$data['gateway'],$data['idempotency_key']);
        return redirect()->away((string)$result['authorization_url']);
    }

    public function storePayout(Request $request): RedirectResponse
    {
        $data=$request->validate(['provider'=>['required','in:bank,paystack,manual'],'account_name'=>['required','string','max:255'],'account_number'=>['required','string','max:40'],'bank_code'=>['nullable','string','max:30'],'bank_name'=>['nullable','string','max:255'],'currency'=>['required','string','size:3'],'is_default'=>['nullable','boolean']]);
        $data['currency']=strtoupper((string)$data['currency']);
        if($data['is_default']??false)$request->user()->payoutAccounts()->update(['is_default'=>false]); $request->user()->payoutAccounts()->create($data+['is_verified'=>false,'metadata'=>['verification_required'=>true]]);
        return back()->with('success','Payout account saved. An administrator must verify it before withdrawals.');
    }

    public function verifyPayout(Request $request,PayoutAccount $account): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(),403); if(!$request->user()->isSuperAdmin())abort_unless($account->user->university_id===$request->user()->university_id,403);
        $data=$request->validate(['verified'=>['required','boolean']]); $account->update(['is_verified'=>(bool)$data['verified'],'metadata'=>array_merge($account->metadata??[],['verified_by'=>$request->user()->id,'verified_at'=>now()->toIso8601String()])]);
        return back()->with('success','Payout account verification updated.');
    }

    public function requestWithdrawal(Request $request,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['payout_account_id'=>['required','integer','exists:payout_accounts,id'],'amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'idempotency_key'=>['required','string','max:120']]);
        $commerce->requestWithdrawal($request->user(),(int)$data['payout_account_id'],$data['amount'],$data['idempotency_key']); return back()->with('success','Withdrawal request submitted.');
    }

    public function processWithdrawal(Request $request,WithdrawalRequest $withdrawal,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'provider_reference'=>['nullable','required_if:decision,approve','string','max:255'],'note'=>['nullable','string','max:5000']]);
        $commerce->processWithdrawal($withdrawal,$request->user(),$data['decision'],$data['provider_reference']??null,$data['note']??null); return back()->with('success','Withdrawal decision recorded.');
    }

    public function requestRefund(Request $request,CommerceOrder $order,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'reason'=>['required','string','max:5000'],'idempotency_key'=>['required','string','max:120']]);
        $commerce->requestRefund($order,$request->user(),$data['amount'],$data['reason'],$data['idempotency_key']); return back()->with('success','Refund request submitted.');
    }

    public function processRefund(Request $request,CommerceRefund $refund,CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'note'=>['nullable','string','max:5000']]);
        $commerce->processRefund($refund,$request->user(),$data['decision']==='approve',$data['note']??null);
        return back()->with('success','Refund decision recorded.');
    }

    public function reconcileRefund(Request $request, CommerceRefund $refund, CommerceService $commerce): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:confirmed,not_refunded'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:5000'],
        ]);
        $commerce->reconcileRefund($refund, $request->user(), $data['outcome'], $data['provider_reference'] ?? null, $data['note']);

        return back()->with('success', $data['outcome'] === 'confirmed'
            ? 'Refund reconciliation confirmed and local accounting finalized.'
            : 'Refund marked as not completed by the provider. It may now be reviewed again.');
    }
    private function assertTransactionAccess(Request $request, Transaction $transaction): void
    {
        $actor=$request->user();
        if($transaction->user_id===$actor->id) return;
        abort_unless($actor->isAdmin(),403);
        if(!$actor->isSuperAdmin()){
            $transaction->loadMissing('user');
            abort_unless($transaction->user?->university_id===$actor->university_id,403);
        }
    }

}
