<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\CommercialAccountRequest;
use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\PayoutAccount;
use App\Models\CommerceRevenueAllocation;
use App\Models\FeatureUsageCharge;
use App\Models\PricingRule;
use App\Models\University;
use App\Models\WithdrawalRequest;
use App\Services\NotificationService;
use App\Services\SettingService;
use App\Enums\NotificationType;
use App\Services\Commerce\LedgerService;
use App\Services\Commerce\PricingCatalogService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        abort_unless($request->user()->isSuperAdmin(),403);
        $request->validate(['account_q' => ['nullable', 'string', 'max:100']]);
        $scope=null;
        $displayCurrency=strtoupper((string)SettingService::getGlobal('currency','NGN'));
        $settings=[]; foreach(self::KEYS as $key=>$type)$settings[$key]=SettingService::getGlobal($key,$this->default($key));
        $pricingRules=PricingRule::query()->latest()->latest('id')->paginate(50, ['*'], 'pricing_page')->withQueryString();
        $pricingRuleTitles=$pricingRules->mapWithKeys(function (PricingRule $rule): array {
            if (str_starts_with($rule->key, 'ai.')) {
                $featureKey=(string)data_get($rule->metadata,'ai_feature',substr($rule->key,3));
                return [$rule->id => (string)data_get(config('monetization.ai_features.'.$featureKey,[]),'title',$rule->name)];
            }
            $featureKey=(string)data_get($rule->metadata,'feature',str_starts_with($rule->key,'feature.') ? substr($rule->key,8) : $rule->key);
            return [$rule->id => (string)data_get(config('monetization.pricing_features.'.$featureKey,[]),'title',$rule->name)];
        });
        $pricingFeatures=collect((array) config('monetization.pricing_features', []))
            ->map(fn(array $feature,string $key): array => [...$feature,'key'=>$key,'target'=>'feature:'.$key,'rule_key'=>'feature.'.$key])
            ->values();
        $aiPricingFeatures=collect(app(PricingCatalogService::class)->aiDefinitions())
            ->map(fn(array $feature,string $key): array => [...$feature,'key'=>$key,'target'=>'ai:'.$key,'pricing_type'=>'ai_usage','rule_key'=>'ai.'.$key])
            ->values();
        $pricingTargets=$pricingFeatures->concat($aiPricingFeatures)->values();
        $commercialAccounts=CommercialAccount::query()->with(['university:id,name','department:id,name'])
            ->when($scope,fn($q)=>$q->where('university_id',$scope))
            ->when($search = trim((string)$request->input('account_q','')), fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$search.'%')->orWhereHas('university',fn($q)=>$q->where('name','like','%'.$search.'%'))))
            ->latest()->latest('id')->paginate(20, ['*'], 'accounts_page')->withQueryString();
        $selectedUniversity=app(\App\Services\AdminLookupService::class)->selected($request->user(),'commercial_universities',old('university_id'));
        $pendingCommercialRequests=$request->user()->isSuperAdmin()
            ? CommercialAccountRequest::query()->with(['university','department','requester'])->whereIn('status',['pending','in_review'])->oldest()->limit(50)->get()
            : collect();
        $setupRequest=null;
        if($request->user()->isSuperAdmin() && $request->integer('commercial_request')){
            $setupRequest=CommercialAccountRequest::query()->with(['university','department','commercialAccount'])->whereKey($request->integer('commercial_request'))->whereIn('status',['pending','in_review'])->first();
        }
        $setupUniversityId=(int)($setupRequest?->university_id ?: $request->integer('institution'));
        $setupDepartmentId=(int)($setupRequest?->department_id ?: $request->integer('department'));
        // Resolve the edited account independently of the visible list page.
        // Balances and negotiated terms must always come from the current row.
        $setupAccount=($setupDepartmentId>0 || $setupUniversityId>0)
            ? CommercialAccount::query()->with('university:id,name')->when($setupDepartmentId>0,fn($q)=>$q->where('department_id',$setupDepartmentId),fn($q)=>$q->where('university_id',$setupUniversityId)->whereNull('department_id'))->first()
            : null;
        if($setupDepartmentId>0 && !$setupUniversityId)$setupUniversityId=(int)\App\Models\Department::findOrFail($setupDepartmentId)->faculty->university_id;
        $setupCurrency=$setupAccount?->currency ?: ($setupUniversityId>0 ? strtoupper((string)SettingService::get('currency',$displayCurrency,$setupUniversityId)) : $displayCurrency);
        $setupCurrency=strtoupper(trim((string)$setupCurrency)) ?: 'NGN';

        // Prepare every commercial-account form value in the controller rather than
        // relying on Blade-local @php state. This keeps the view deterministic even
        // when middleware is disabled by feature/regression tests.
        $pricingFeatureGroups=$pricingTargets->groupBy('category');
        $setupChanges=(array)($setupRequest?->requested_changes ?? []);
        $setupMetadata=(array)($setupAccount?->metadata ?? []);
        $requestedAddMinor=$setupRequest?->request_type === 'add_funds'
            ? (int)($setupRequest->requested_amount_minor ?? 0)
            : 0;
        $suggestedTargetMinor=(int)($setupAccount?->prepaid_balance_minor ?? 0)+$requestedAddMinor;
        $selectedSponsoredFeatures=$setupRequest?->request_type === 'sponsorship'
            ? (array)data_get($setupChanges,'sponsored_features',[])
            : (array)data_get($setupMetadata,'sponsored_features',[]);

        $platformAllocations=CommerceRevenueAllocation::query()->where('allocation_type','platform')
            ->when($scope,fn($q)=>$q->whereHas('orderItem.order',fn($oq)=>$oq->where('university_id',$scope)))
            ->whereHas('orderItem.order',fn($oq)=>$oq->where('currency',$displayCurrency))->select(['id','amount_minor','metadata']);
        // Retain per-allocation reversal semantics, with bounded memory even
        // when the ledger contains years of entries. Never cache balances.
        $platformRevenueMinor=(int)$platformAllocations->lazyById(500)->sum(fn($allocation)=>max(0,(int)$allocation->amount_minor-(int)data_get($allocation->metadata,'reversed_amount_minor',0)));
        $aiTotals=AiUsageCharge::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->where('status','completed')
            ->selectRaw('SUM(user_charge_minor) AS revenue, SUM(platform_margin_minor) AS margin')->first();
        $metrics=[
            'paid_orders'=>CommerceOrder::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('payment_status','paid')->count(),
            'gross_minor'=>(int)CommerceOrder::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->whereIn('payment_status',['paid','partially_refunded'])->sum('total_amount_minor'),
            'platform_revenue_minor'=>$platformRevenueMinor,
            'pending_withdrawals'=>WithdrawalRequest::query()->when($scope,fn($q)=>$q->whereHas('wallet.user',fn($uq)=>$uq->where('university_id',$scope)))->where('status','pending')->count(),
            'feature_revenue_minor'=>(int)FeatureUsageCharge::query()->when($scope,fn($q)=>$q->where('university_id',$scope))->where('currency',$displayCurrency)->where('status','completed')->sum('total_amount_minor'),
            'ai_revenue_minor'=>(int)$aiTotals?->revenue,
            'ai_margin_minor'=>(int)$aiTotals?->margin,
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
        return view('admin.monetization.index',compact(
            'settings','pricingRules','pricingRuleTitles','pricingFeatures','aiPricingFeatures','pricingTargets','pricingFeatureGroups','commercialAccounts','selectedUniversity',
            'metrics','scope','displayCurrency','pendingWithdrawals','pendingRefunds','pendingPayoutAccounts',
            'pendingCommercialRequests','setupRequest','setupUniversityId','setupAccount','setupCurrency',
            'setupDepartmentId','setupChanges','setupMetadata','requestedAddMinor','suggestedTargetMinor','selectedSponsoredFeatures'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403); $scope=null;
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

        $featureDefinitions=(array) config('monetization.pricing_features', []);
        $aiDefinitions=app(PricingCatalogService::class)->aiDefinitions();
        $allowedTargets=array_merge(
            array_map(fn(string $key): string => 'feature:'.$key, array_keys($featureDefinitions)),
            array_map(fn(string $key): string => 'ai:'.$key, array_keys($aiDefinitions)),
        );

        // Accept the old `feature` field for backwards-compatible tests/forms,
        // but normalize every new request to an explicit target.
        $target=(string)($request->input('target') ?: ($request->filled('feature') ? 'feature:'.$request->input('feature') : ''));
        $request->merge(['target'=>$target]);

        $data=$request->validate([
            'target'=>['required','string',Rule::in($allowedTargets)],
            'amount'=>['required','regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],
            'duration_days'=>['nullable','integer','min:0','max:3650'],
            'semester_amount'=>['nullable','regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],
            'semester_duration_days'=>['nullable','integer','min:1','max:3650'],
            'included_ai_requests'=>['nullable','integer','min:0','max:100000'],
            'semester_included_ai_requests'=>['nullable','integer','min:0','max:100000'],
            'trial_days'=>['nullable','integer','min:0','max:90'],
            'trial_included_ai_requests'=>['nullable','integer','min:0','max:10000'],
            'grace_days'=>['nullable','integer','min:0','max:30'],
            'free_daily_requests'=>['nullable','integer','min:0','max:10000'],
            'enabled'=>['nullable','boolean'],
        ],[
            'target.required'=>'Choose the feature or AI assistant you want to price.',
            'target.in'=>'Choose a valid runtime-connected pricing target.',
            'amount.required'=>'Enter the amount users should pay.',
            'amount.regex'=>'Enter a valid amount with no more than 2 decimal places.',
        ]);

        $amountMinor=Money::toMinor((string)$data['amount']);
        if($amountMinor<=0){
            return back()->withErrors(['amount'=>'Price must be greater than zero.'])->withInput();
        }

        [$kind,$featureKey]=explode(':',(string)$data['target'],2);
        $isAi=$kind==='ai';
        $definition=$isAi ? $aiDefinitions[$featureKey] : $featureDefinitions[$featureKey];
        $pricingType=$isAi ? 'ai_usage' : (string)($definition['pricing_type']??'fixed_access');
        $ruleKey=($isAi?'ai.':'feature.').$featureKey;
        $ruleName=(string)($definition['title'] ?? str($featureKey)->replace('_',' ')->title());
        $currency=strtoupper((string)SettingService::getGlobal('currency','NGN'));

        $metadata=[
            'managed_from'=>'admin_monetization',
            'changed_by_user_id'=>$request->user()->id,
            'pricing_type'=>$pricingType,
            'unit_label'=>$definition['unit_label']??($isAi?'AI request':'use'),
            'feature_category'=>$definition['category']??null,
        ];
        if($isAi){
            $metadata['ai_feature']=$featureKey;
            $metadata['free_daily_requests']=max(0,(int)($data['free_daily_requests']??0));
        }else{
            $metadata['feature']=$featureKey;
            if($pricingType==='fixed_access'){
                $metadata['duration_days']=max(0,(int)($data['duration_days']??($definition['default_duration_days']??0)));
                if((bool)($definition['package']??false)){
                    $metadata['semester_amount_minor']=max(0,Money::toMinor((string)($data['semester_amount']??'0')));
                    $metadata['semester_duration_days']=max(1,(int)($data['semester_duration_days']??($definition['default_semester_duration_days']??120)));
                    $metadata['included_ai_requests']=max(0,(int)($data['included_ai_requests']??0));
                    $metadata['semester_included_ai_requests']=max(0,(int)($data['semester_included_ai_requests']??0));
                    $metadata['trial_days']=max(0,(int)($data['trial_days']??($definition['default_trial_days']??0)));
                    $metadata['trial_included_ai_requests']=max(0,(int)($data['trial_included_ai_requests']??0));
                    $metadata['grace_days']=max(0,(int)($data['grace_days']??($definition['default_grace_days']??0)));
                    $metadata['package']=true;
                }
            }
        }

        DB::transaction(function () use ($request,$ruleKey,$ruleName,$currency,$amountMinor,$metadata): void {
            $current=PricingRule::query()->where('key',$ruleKey)->where('scope_type','global')->where('scope_id',0)->lockForUpdate()->orderByDesc('version')->first();
            $nextVersion=((int)($current?->version ?? 0))+1;
            PricingRule::query()->where('key',$ruleKey)->where('scope_type','global')->where('scope_id',0)->where('enabled',true)->update(['enabled'=>false,'ends_at'=>now()]);
            PricingRule::query()->create([
                'uuid'=>(string)Str::uuid(),'key'=>$ruleKey,'name'=>$ruleName,'scope_type'=>'global','scope_id'=>0,'version'=>$nextVersion,'supersedes_id'=>$current?->id,
                'currency'=>$currency,'unit_amount_minor'=>$amountMinor,'percentage_basis_points'=>null,'enabled'=>$request->boolean('enabled'),
                'metadata'=>$metadata,'starts_at'=>now(),
            ]);
        },3);

        return back()->with('success','A new immutable pricing-rule version was published and is connected to runtime billing.');
    }

    public function storeCommercialAccount(Request $request, LedgerService $ledger, NotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $featureKeys=array_keys((array) config('monetization.pricing_features', []));
        $data=$request->validate([
            'university_id'=>['required','integer','exists:universities,id'],
            'department_id'=>['nullable','integer','exists:departments,id'],
            'plan_key'=>['nullable',Rule::in(array_keys(config('academic_adoption.plans')))],
            'access_ends_at'=>['nullable','date'],
            'name'=>['nullable','string','max:255'],
            'status'=>['nullable',Rule::in(['active','paused'])],
            'prepaid_balance'=>['required','regex:/^\d+(?:\.\d{1,2})?$/'],
            'max_administrators'=>['nullable','integer','min:1','max:10000'],
            'sponsor_ai_usage'=>['nullable','boolean'],
            'sponsored_features'=>['nullable','array'],
            'sponsored_features.*'=>['string',Rule::in($featureKeys)],
            'student_semester_fee'=>['nullable','regex:/^\d+(?:\.\d{1,2})?$/'],
            'invoice_grace_days'=>['nullable','integer','min:0','max:90'],
            'request_id'=>['nullable','integer','exists:commercial_account_requests,id'],
        ]);
        $targetBalance=Money::toMinor($data['prepaid_balance']);
        $universityId=(int)$data['university_id'];
        $university=University::query()->findOrFail($universityId);
        $departmentId=!empty($data['department_id'])?(int)$data['department_id']:null;
        $department=$departmentId?\App\Models\Department::with('faculty')->findOrFail($departmentId):null;
        abort_if($department && (int)$department->faculty->university_id!==$universityId,422,'The commercial department belongs to another institution.');
        if(!empty($data['plan_key']))abort_unless(config('academic_adoption.plans.'.$data['plan_key'].'.scope')===($departmentId?'department':'institution'),422,'Choose a plan for this organisation scope.');
        $accountScope=$departmentId?['department_id'=>$departmentId]:['university_id'=>$universityId,'department_id'=>null];
        $existingAccount=CommercialAccount::query()->where($accountScope)->first();
        $currency=strtoupper(trim((string)($existingAccount?->currency ?: SettingService::get('currency','NGN',$universityId)))) ?: 'NGN';
        $relatedRequest=null;
        if(!empty($data['request_id'])){
            $relatedRequest=CommercialAccountRequest::query()->whereKey((int)$data['request_id'])->where('university_id',$universityId)->where('department_id',$departmentId)->whereIn('status',['pending','in_review'])->firstOrFail();
        }

        DB::transaction(function () use ($request,$data,$targetBalance,$universityId,$currency,$ledger,$university,$department,$departmentId,$accountScope,$relatedRequest): void {
            if($department)\App\Models\Department::whereKey($department->id)->lockForUpdate()->firstOrFail();
            else University::whereKey($universityId)->lockForUpdate()->firstOrFail();
            if($relatedRequest){$relatedRequest=CommercialAccountRequest::query()->lockForUpdate()->findOrFail($relatedRequest->id);abort_unless(in_array($relatedRequest->status,['pending','in_review'],true),422,'This request was already reviewed.');}
            $account=CommercialAccount::query()->where($accountScope)->lockForUpdate()->first();
            $previous=(int)($account?->prepaid_balance_minor ?? 0);
            $existingMetadata=(array)($account?->metadata ?? []);
            if(!$account){
                $account=new CommercialAccount(['uuid'=>(string)Str::uuid(),'university_id'=>$departmentId?null:$universityId,'department_id'=>$departmentId]);
            }
            $account->fill([
                'name'=>trim((string)($data['name']??'')) ?: ($department?->name ?? $university->name).' Commercial Account',
                'currency'=>$currency,
                'plan_key'=>$data['plan_key']??$account->plan_key??($departmentId?'department_core':'institution_core'),
                'access_ends_at'=>$data['access_ends_at']??null,
                'prepaid_balance_minor'=>$targetBalance,
                'status'=>$data['status']??'active',
                'metadata'=>array_merge($existingMetadata,[
                    'max_administrators'=>$data['max_administrators']??null,
                    'sponsor_ai_usage'=>$request->boolean('sponsor_ai_usage'),
                    'sponsored_features'=>array_values($data['sponsored_features']??[]),
                    'student_semester_fee_minor'=>isset($data['student_semester_fee'])&&$data['student_semester_fee']!==''?Money::toMinor((string)$data['student_semester_fee']):null,
                    'invoice_grace_days'=>$data['invoice_grace_days']??7,
                    'last_changed_by_user_id'=>$request->user()->id,
                ]),
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
            if($relatedRequest){
                $relatedRequest->forceFill([
                    'commercial_account_id'=>$account->id,
                    'status'=>'approved',
                    'reviewed_by_user_id'=>$request->user()->id,
                    'reviewed_at'=>now(),
                    'review_note'=>'Approved and applied to the institution commercial account.',
                ])->save();
            }
        },3);

        if($relatedRequest?->requester){
            $notifications->send(
                $relatedRequest->requester,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                'Commercial request approved',
                'Platform Admin approved your '.$relatedRequest->label().' and updated the institution commercial account.',
                ['route'=>'institution.commercial-account','commercial_account_request_id'=>$relatedRequest->id]
            );
        }

        return redirect()->route('admin.monetization')->with('success','Commercial account saved. Approved terms are now visible to the institution admin, and prepaid-balance changes were posted to the audit ledger.');
    }

    public function rejectCommercialAccountRequest(Request $request, CommercialAccountRequest $commercialRequest, NotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['review_note'=>['required','string','max:2000']],[
            'review_note.required'=>'Add a short explanation so the institution knows why this request was not approved.',
        ]);
        abort_unless(in_array($commercialRequest->status,['pending','in_review'],true),422,'This request has already been reviewed.');
        $commercialRequest->forceFill([
            'status'=>'rejected',
            'reviewed_by_user_id'=>$request->user()->id,
            'reviewed_at'=>now(),
            'review_note'=>$data['review_note'],
        ])->save();
        $commercialRequest->loadMissing('requester');
        if($commercialRequest->requester){
            $notifications->send(
                $commercialRequest->requester,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                'Commercial request reviewed',
                'Platform Admin reviewed your '.$commercialRequest->label().'. Open Commercial Account to see the decision note.',
                ['route'=>'institution.commercial-account','commercial_account_request_id'=>$commercialRequest->id]
            );
        }
        return back()->with('success','Commercial request rejected with a visible review note for the institution.');
    }

    private function default(string $key): mixed
    {
        return match($key){'wallet_minimum_funding_amount'=>'500','minimum_withdrawal_amount'=>'1000','withdrawal_fee_percentage'=>'0','knowledge_platform_commission_percentage'=>'15','knowledge_institution_revenue_percentage'=>'0','creator_earnings_hold_days'=>3,'ai_monetization_enabled'=>false,'ai_free_daily_requests'=>3,'ai_request_charge_amount'=>'25','ai_local_currency_per_usd_reporting'=>'1500',default=>null};
    }
}
