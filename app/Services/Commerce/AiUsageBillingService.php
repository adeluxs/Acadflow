<?php

declare(strict_types=1);
namespace App\Services\Commerce;

use App\Ai\Contracts\AiResponse;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\User;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiUsageBillingService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
    ) {}

    /** @return array<string,mixed> */
    public function reserve(?User $user,string $feature,string $requestId): array
    {
        if(!$user || !(bool)SettingService::get('ai_monetization_enabled',false,$user->university_id)) return ['source'=>'platform','charge_minor'=>0,'request_id'=>$requestId];
        $free=max(0,(int)SettingService::get('ai_free_daily_requests',3,$user->university_id));
        $used=AiUsageCharge::query()->where('user_id',$user->id)->whereDate('created_at',today())->where('status','completed')->count();
        if($used<$free) return ['source'=>'free_allowance','charge_minor'=>0,'request_id'=>$requestId];

        $charge=Money::toMinor((string)SettingService::get('ai_request_charge_amount','25',$user->university_id));
        if($charge<=0) return ['source'=>'platform','charge_minor'=>0,'request_id'=>$requestId];

        $commercial=CommercialAccount::query()->where('university_id',$user->university_id)->where('status','active')->first();
        if($commercial && (bool)data_get($commercial->metadata,'sponsor_ai_usage',false)){
            return DB::transaction(function() use($commercial,$charge,$requestId,$user){
                $locked=CommercialAccount::query()->whereKey($commercial->id)->lockForUpdate()->firstOrFail();
                if((int)$locked->prepaid_balance_minor<$charge) throw ValidationException::withMessages(['ai'=>'Your institution AI allowance is currently exhausted.']);
                $locked->decrement('prepaid_balance_minor',$charge);
                $this->ledger->post('ai-reserve:'.$requestId.':institution','ai_usage_reserve',$locked->currency?:'NGN',[
                    ['account_code'=>'institution_prepaid_liability:'.$locked->id,'direction'=>'debit','amount_minor'=>$charge],
                    ['account_code'=>'ai_revenue_clearing','direction'=>'credit','amount_minor'=>$charge],
                ],$user,['commercial_account_id'=>$locked->id,'ai_request_id'=>$requestId]);
                return ['source'=>'institution','commercial_account_id'=>$locked->id,'charge_minor'=>$charge,'request_id'=>$requestId,'currency'=>$locked->currency?:'NGN'];
            },3);
        }

        if($this->wallets->totalSpendableMinor($user)<$charge){
            throw ValidationException::withMessages(['ai'=>'Your balance is too low for this AI request. Add funds and try again.']);
        }
        $entry=$this->wallets->debitSpending($user,$charge,'ai_reserve',null,null,'AI request funds reserved.',['ai_request_id'=>$requestId,'feature'=>$feature]);
        return ['source'=>'user_wallet','charge_minor'=>$charge,'request_id'=>$requestId,'currency'=>$entry->wallet?->currency ?? 'NGN'];
    }

    public function release(?User $user,array $reservation): void
    {
        $charge=(int)($reservation['charge_minor']??0); if($charge<=0||!$user)return;
        if(($reservation['source']??null)==='user_wallet'){
            $this->wallets->creditSpending($user,$charge,'ai_release',null,null,'AI request reservation released.',['ai_request_id'=>$reservation['request_id']??null]);
        } elseif(($reservation['source']??null)==='institution' && !empty($reservation['commercial_account_id'])){
            DB::transaction(function() use($reservation,$charge,$user){
                $account=CommercialAccount::query()->whereKey((int)$reservation['commercial_account_id'])->lockForUpdate()->firstOrFail();
                $account->increment('prepaid_balance_minor',$charge);
                $requestId=(string)($reservation['request_id']??'unknown');
                $this->ledger->post('ai-release:'.$requestId.':institution','ai_usage_release',$account->currency?:'NGN',[
                    ['account_code'=>'ai_revenue_clearing','direction'=>'debit','amount_minor'=>$charge],
                    ['account_code'=>'institution_prepaid_liability:'.$account->id,'direction'=>'credit','amount_minor'=>$charge],
                ],$user,['commercial_account_id'=>$account->id,'ai_request_id'=>$requestId]);
            },3);
        }
    }

    public function settle(?User $user,string $feature,AiResponse $response,array $reservation): void
    {
        if(!$response->success){$this->release($user,$reservation);return;}
        $charge=(int)($reservation['charge_minor']??0);
        // Provider adapters attach authoritative integer micro-USD cost metadata.
        // The legacy float `cost` field is retained only for backwards-compatible
        // analytics and is never used for a new financial calculation.
        $providerCostMicroUsd = max(0, (int) ($response->metadata['provider_cost_micro_usd'] ?? 0));
        $fxMinor=Money::toMinor((string)SettingService::get('ai_local_currency_per_usd_reporting','1500',$user?->university_id));
        $providerCostLocalMinor=intdiv(($providerCostMicroUsd*$fxMinor)+500_000,1_000_000);
        $usage=AiUsageCharge::query()->firstOrCreate(['request_id'=>$response->requestId],[
            'user_id'=>$user?->id,'university_id'=>$user?->university_id,'feature'=>$feature,'provider'=>$response->provider,'model'=>$response->model,
            'input_tokens'=>max(0,(int)($response->inputTokens??0)),'output_tokens'=>max(0,(int)($response->outputTokens??0)),
            'provider_cost_micro_usd'=>$providerCostMicroUsd,'user_charge_minor'=>$charge,'platform_margin_minor'=>$charge-$providerCostLocalMinor,
            'currency'=>strtoupper((string)($reservation['currency']??SettingService::get('currency','NGN',$user?->university_id))),'status'=>'completed','metadata'=>['funding_source'=>$reservation['source']??'platform','provider_cost_local_minor'=>$providerCostLocalMinor],
        ]);
        if($charge>0 && in_array(($reservation['source']??null),['user_wallet','institution'],true)){
            $this->ledger->post('ai-settle:'.$response->requestId,'ai_usage_settlement',(string)($reservation['currency']??'NGN'),[
                ['account_code'=>'ai_revenue_clearing','direction'=>'debit','amount_minor'=>$charge],
                ['account_code'=>'platform_ai_revenue','direction'=>'credit','amount_minor'=>$charge],
            ],$user,['ai_usage_charge_id'=>$usage->id,'provider'=>$response->provider,'model'=>$response->model]);
        }
    }
}
