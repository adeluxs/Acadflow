<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\PayoutAccount;
use App\Models\CommerceRevenueAllocation;
use App\Models\PricingRule;
use App\Models\University;
use App\Models\WithdrawalRequest;
use App\Services\SettingService;
use App\Services\Commerce\LedgerService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonetizationController extends Controller
{
    private const KEYS=[
        'wallet_minimum_funding_amount'=>'decimal','minimum_withdrawal_amount'=>'decimal','withdrawal_fee_percentage'=>'decimal',
        'knowledge_platform_commission_percentage'=>'decimal','knowledge_institution_revenue_percentage'=>'decimal','creator_earnings_hold_days'=>'integer',
        'ai_monetization_enabled'=>'boolean','ai_free_daily_requests'=>'integer','ai_request_charge_amount'=>'decimal','ai_local_currency_per_usd_reporting'=>'decimal',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(),403);
        $scope=$request->user()->isSuperAdmin()?null:$request->user()->university_id;
        $displayCurrency=strtoupper((string)SettingService::get('currency','NGN',$scope));
        $settings=[]; foreach(self::KEYS as $key=>$type)$settings[$key]=SettingService::get($key,$this->default($key),$scope);
        $pricingRules=PricingRule::query()->latest()->limit(100)->get();
        $commercialAccounts=CommercialAccount::query()->with('university')->when($scope,fn($q)=>$q->where('university_id',$scope))->latest()->get();
        $universities=$request->user()->isSuperAdmin()?University::query()->orderBy('name')->get(['id','name']):collect();
        $platformAllocations=CommerceRevenueAllocation::query()->where('allocation_type','platform')
            ->when($scope,fn($q)=>$q->whereHas('orderItem.order',fn($oq)=>$oq->where('university_id',$scope)))
            ->whereHas('orderItem.order',fn($oq)=>$oq->where('currency',$displayCurrency))->get(['amount_minor','metadata']);
        $platformRevenueMinor=(int)$platformAllocations->sum(fn($allocation)=>max(0,(int)$allocation->amount_minor-(int)data_get($allocation->metadata,'reversed_amount_minor',0)));
        $metrics=[
            'paid_orders'=>CommerceOrder::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('payment_status','paid')->count(),
            'gross_minor'=>(int)CommerceOrder::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->whereIn('payment_status',['paid','partially_refunded'])->sum('total_amount_minor'),
            'platform_revenue_minor'=>$platformRevenueMinor,
            'pending_withdrawals'=>WithdrawalRequest::query()->when($scope,fn($q)=>$q->whereHas('wallet.user',fn($uq)=>$uq->where('university_id',$scope)))->where('status','pending')->count(),
            'ai_revenue_minor'=>(int)AiUsageCharge::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->sum('user_charge_minor'),
            'ai_margin_minor'=>(int)AiUsageCharge::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->sum('platform_margin_minor'),
        ];
        $pendingWithdrawals=WithdrawalRequest::query()->with(['wallet.user','payoutAccount'])
            ->when($scope,fn($q)=>$q->whereHas('wallet.user',fn($uq)=>$uq->where('university_id',$scope)))
            ->where('status','pending')->oldest()->limit(50)->get();
        $pendingRefunds=CommerceRefund::query()->with(['order.buyer','requester'])
            ->when($scope,fn($q)=>$q->whereHas('order',fn($oq)=>$oq->where('university_id',$scope)))
            ->whereIn('status',['requested','processing'])->oldest()->limit(50)->get();
        $pendingPayoutAccounts=PayoutAccount::query()->with('user')
            ->when($scope,fn($q)=>$q->whereHas('user',fn($uq)=>$uq->where('university_id',$scope)))
            ->where('is_verified',false)->latest()->limit(50)->get();
        return view('admin.monetization.index',compact('settings','pricingRules','commercialAccounts','universities','metrics','scope','displayCurrency','pendingWithdrawals','pendingRefunds','pendingPayoutAccounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(),403); $scope=$request->user()->isSuperAdmin()?null:$request->user()->university_id;
        $data=$request->validate([
            'wallet_minimum_funding_amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'minimum_withdrawal_amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],
            'withdrawal_fee_percentage'=>['required','numeric','min:0','max:100'],'knowledge_platform_commission_percentage'=>['required','numeric','min:0','max:100'],
            'knowledge_institution_revenue_percentage'=>['required','numeric','min:0','max:100'],'creator_earnings_hold_days'=>['required','integer','min:0','max:90'],
            'ai_free_daily_requests'=>['required','integer','min:0','max:10000'],'ai_request_charge_amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'ai_local_currency_per_usd_reporting'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],
        ]);
        $platformBasisPoints = Money::toMinor((string) $data['knowledge_platform_commission_percentage']);
        $institutionBasisPoints = Money::toMinor((string) $data['knowledge_institution_revenue_percentage']);
        if (($platformBasisPoints + $institutionBasisPoints) > 10000) {
            return back()->withErrors(['knowledge_institution_revenue_percentage'=>'Platform and institution commissions together cannot exceed 100%.'])->withInput();
        }
        foreach(self::KEYS as $key=>$type){$value=$key==='ai_monetization_enabled'?$request->boolean($key):($data[$key]??$this->default($key)); SettingService::set($key,$value,$type,$scope,$request->user()->id);}
        return back()->with('success','Monetization settings updated and are now authoritative at runtime.');
    }

    public function storePricingRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['key'=>['required','string','max:120'],'name'=>['required','string','max:255'],'amount'=>['nullable','regex:/^\d+(?:\.\d{1,2})?$/'],'percentage'=>['nullable','numeric','min:0','max:100'],'currency'=>['required','string','size:3'],'enabled'=>['nullable','boolean']]);
        DB::transaction(function () use ($data,$request): void {
            $current=PricingRule::query()->where('key',$data['key'])->where('scope_type','global')->where('scope_id',0)->lockForUpdate()->orderByDesc('version')->first();
            $nextVersion=((int)($current?->version ?? 0))+1;
            PricingRule::query()->where('key',$data['key'])->where('scope_type','global')->where('scope_id',0)->where('enabled',true)->update(['enabled'=>false,'ends_at'=>now()]);
            PricingRule::query()->create([
                'uuid'=>(string)Str::uuid(),'key'=>$data['key'],'name'=>$data['name'],'scope_type'=>'global','scope_id'=>0,'version'=>$nextVersion,'supersedes_id'=>$current?->id,
                'currency'=>strtoupper($data['currency']),'unit_amount_minor'=>isset($data['amount'])?Money::toMinor($data['amount']):null,
                'percentage_basis_points'=>isset($data['percentage'])?Money::toMinor((string)$data['percentage']):null,'enabled'=>$request->boolean('enabled'),'metadata'=>['managed_from'=>'admin_monetization','changed_by_user_id'=>$request->user()->id],
                'starts_at'=>now(),
            ]);
        },3);
        return back()->with('success','A new immutable pricing-rule version was published. Previous versions remain available for audit.');
    }

    public function storeCommercialAccount(Request $request, LedgerService $ledger): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['university_id'=>['required','integer','exists:universities,id'],'name'=>['required','string','max:255'],'prepaid_balance'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],'max_administrators'=>['nullable','integer','min:1','max:10000'],'sponsor_ai_usage'=>['nullable','boolean'],'student_semester_fee'=>['nullable','regex:/^\d+(?:\.\d{1,2})?$/'],'invoice_grace_days'=>['nullable','integer','min:0','max:90']]);
        $targetBalance=Money::toMinor($data['prepaid_balance']);
        $universityId=(int)$data['university_id'];
        $currency=strtoupper((string)SettingService::get('currency','NGN',$universityId));
        DB::transaction(function () use ($request,$data,$targetBalance,$universityId,$currency,$ledger): void {
            $account=CommercialAccount::query()->where('university_id',$universityId)->lockForUpdate()->first();
            $previous=(int)($account?->prepaid_balance_minor ?? 0);
            if(!$account){
                $account=new CommercialAccount(['uuid'=>(string)Str::uuid(),'university_id'=>$universityId]);
            }
            $account->fill([
                'name'=>$data['name'],'currency'=>$currency,'prepaid_balance_minor'=>$targetBalance,'status'=>'active',
                'metadata'=>['max_administrators'=>$data['max_administrators']??null,'sponsor_ai_usage'=>$request->boolean('sponsor_ai_usage'),'student_semester_fee_minor'=>isset($data['student_semester_fee'])?Money::toMinor($data['student_semester_fee']):null,'invoice_grace_days'=>$data['invoice_grace_days']??7],
            ]);
            $account->save();
            $delta=$targetBalance-$previous;
            if($delta!==0){
                $amount=abs($delta);
                $liability='institution_prepaid_liability:'.$account->id;
                $postings=$delta>0
                    ? [['account_code'=>'business_prepaid_funding_clearing','direction'=>'debit','amount_minor'=>$amount],['account_code'=>$liability,'direction'=>'credit','amount_minor'=>$amount]]
                    : [['account_code'=>$liability,'direction'=>'debit','amount_minor'=>$amount],['account_code'=>'business_prepaid_adjustments','direction'=>'credit','amount_minor'=>$amount]];
                $ledger->post('commercial-account-balance:'.$account->id.':'.Str::uuid(),'commercial_prepaid_balance_adjustment',$currency,$postings,$request->user(),[
                    'commercial_account_id'=>$account->id,'university_id'=>$universityId,'previous_minor'=>$previous,'target_minor'=>$targetBalance,'admin_user_id'=>$request->user()->id,
                    'note'=>'Administrative contractual prepaid-balance adjustment; reconcile funding-clearing against the underlying contract/payment.',
                ]);
            }
        },3);
        return back()->with('success','Commercial account saved. Any prepaid-balance change was posted to the audit ledger.');
    }

    private function default(string $key): mixed
    {
        return match($key){'wallet_minimum_funding_amount'=>'500','minimum_withdrawal_amount'=>'1000','withdrawal_fee_percentage'=>'0','knowledge_platform_commission_percentage'=>'15','knowledge_institution_revenue_percentage'=>'0','creator_earnings_hold_days'=>3,'ai_monetization_enabled'=>false,'ai_free_daily_requests'=>3,'ai_request_charge_amount'=>'25','ai_local_currency_per_usd_reporting'=>'1500',default=>null};
    }
}
