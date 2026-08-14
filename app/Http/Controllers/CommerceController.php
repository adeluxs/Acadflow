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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CommerceController extends Controller
{
    public function orders(Request $request): View
    {
        $orders = CommerceOrder::query()->with(['items.purchasable','transaction.paymentGateway','refunds'])->when(! $request->user()->isAdmin(), fn($q)=>$q->where('buyer_id',$request->user()->id))->when($request->user()->isUniversityAdmin(), fn($q)=>$q->where('university_id',$request->user()->university_id))->latest()->paginate(25);
        return view('commerce.orders', compact('orders'));
    }

    public function purchase(Request $request, KnowledgePublication $publication, CommerceService $commerce): RedirectResponse
    {
        $this->authorize('view',$publication);
        $data=$request->validate(['gateway'=>['required','string','exists:payment_gateways,code']]);
        $order=$commerce->createOrder($request->user(),$publication,['email'=>$request->user()->email]);
        $result=$commerce->initialize($order,$data['gateway'],route('commerce.callback',['transaction'=>'TRANSACTION_UUID']));
        $callback=route('commerce.callback',$result['transaction']);
        // Some gateways require the final callback URL during initialization. Reinitialize is intentionally avoided;
        // the transaction UUID is also stored in metadata and the webhook remains authoritative.
        return redirect()->away((string)$result['authorization_url']);
    }

    public function purchaseLearningPath(Request $request, LearningPath $path, CommerceService $commerce): RedirectResponse
    {
        $data = $request->validate(['gateway' => ['required', 'string', 'exists:payment_gateways,code']]);
        $order = $commerce->createOrder($request->user(), $path, ['email' => $request->user()->email]);
        $result = $commerce->initialize($order, $data['gateway'], route('commerce.callback', ['transaction' => 'TRANSACTION_UUID']));

        return redirect()->away((string) $result['authorization_url']);
    }

    public function callback(Request $request, Transaction $transaction, CommerceService $commerce): RedirectResponse
    {
        abort_unless($transaction->user_id===$request->user()->id||$request->user()->isAdmin(),403);
        $order=$commerce->verify($transaction);
        return redirect()->route('commerce.orders')->with('success','Payment confirmed. Your access entitlement is active for order '.$order->order_number.'.');
    }

    public function webhook(Request $request, string $gateway, PaymentGatewayManager $gateways, CommerceService $commerce)
    {
        $model=PaymentGateway::query()->where('code',$gateway)->where('is_active',true)->firstOrFail();
        $adapter=$gateways->gateway($gateway);$payload=$request->all();$signature=$request->header('X-Paystack-Signature')??$request->header('X-Webhook-Signature');
        abort_unless($adapter->validateWebhook($payload,$signature),401,'Invalid webhook signature.');
        $reference=(string)data_get($payload,'data.reference','');
        $transaction=Transaction::query()->where('payment_gateway_id',$model->id)->where('gateway_transaction_id',$reference)->first();
        if(!$transaction)return response()->json(['received'=>true,'matched'=>false]);
        try{$commerce->verify($transaction);}catch(\Throwable $e){Log::warning('Commerce webhook verification deferred',['transaction_id'=>$transaction->id,'message'=>$e->getMessage()]);return response()->json(['received'=>true,'verified'=>false],202);}
        return response()->json(['received'=>true,'verified'=>true]);
    }

    public function wallet(Request $request, WalletService $wallets): View
    {
        $wallet=$wallets->account($request->user());$wallet->load(['entries'=>fn($q)=>$q->latest()->limit(100),'withdrawals.payoutAccount']);$accounts=$request->user()->payoutAccounts()->latest()->get();
        $allocations=\App\Models\CommerceRevenueAllocation::query()->with('orderItem.order')->where('beneficiary_user_id',$request->user()->id)->latest()->paginate(25);
        return view('commerce.wallet',compact('wallet','accounts','allocations'));
    }

    public function storePayout(Request $request): RedirectResponse
    {
        $data=$request->validate(['provider'=>['required','in:bank,paystack,manual'],'account_name'=>['required','string','max:255'],'account_number'=>['required','string','max:40'],'bank_code'=>['nullable','string','max:30'],'bank_name'=>['nullable','string','max:255'],'currency'=>['required','string','size:3'],'is_default'=>['nullable','boolean']]);
        if($data['is_default']??false)$request->user()->payoutAccounts()->update(['is_default'=>false]);
        $request->user()->payoutAccounts()->create($data+['is_verified'=>false,'metadata'=>['verification_required'=>true]]);
        return back()->with('success','Payout account saved. An administrator must verify it before withdrawals.');
    }

    public function verifyPayout(Request $request, PayoutAccount $account): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(),403);if(!$request->user()->isSuperAdmin())abort_unless($account->user->university_id===$request->user()->university_id,403);
        $data=$request->validate(['verified'=>['required','boolean']]);$account->update(['is_verified'=>(bool)$data['verified'],'metadata'=>array_merge($account->metadata??[],['verified_by'=>$request->user()->id,'verified_at'=>now()->toIso8601String()])]);
        return back()->with('success','Payout account verification updated.');
    }

    public function requestWithdrawal(Request $request, CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['payout_account_id'=>['required','integer','exists:payout_accounts,id'],'amount'=>['required','numeric','min:1']]);$commerce->requestWithdrawal($request->user(),(int)$data['payout_account_id'],(float)$data['amount']);return back()->with('success','Withdrawal request submitted.');
    }

    public function processWithdrawal(Request $request, WithdrawalRequest $withdrawal, CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'provider_reference'=>['nullable','required_if:decision,approve','string','max:255'],'note'=>['nullable','string','max:5000']]);$commerce->processWithdrawal($withdrawal,$request->user(),$data['decision'],$data['provider_reference']??null,$data['note']??null);return back()->with('success','Withdrawal decision recorded.');
    }

    public function requestRefund(Request $request, CommerceOrder $order, CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['amount'=>['required','numeric','min:0.01'],'reason'=>['required','string','max:5000']]);$commerce->requestRefund($order,$request->user(),(float)$data['amount'],$data['reason']);return back()->with('success','Refund request submitted.');
    }

    public function processRefund(Request $request, CommerceRefund $refund, CommerceService $commerce): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'note'=>['nullable','string','max:5000']]);$commerce->processRefund($refund,$request->user(),$data['decision']==='approve',$data['note']??null);return back()->with('success','Refund decision recorded.');
    }
}
