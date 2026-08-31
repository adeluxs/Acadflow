<?php

declare(strict_types=1);
namespace App\Services\Commerce;

use App\Models\FeatureEntitlement;
use App\Models\PricingRule;
use App\Models\User;

/** Independent feature entitlement engine; payment method is intentionally irrelevant. */
class EntitlementService
{
    public function has(User $user,string $feature): bool
    {
        if($user->isAdmin()) return true;
        $granted=FeatureEntitlement::query()->where('user_id',$user->id)->where('feature',$feature)->where('status','active')
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->exists();
        if($granted) return true;

        // Features remain free unless Admin explicitly publishes a commercial
        // pricing rule for that feature. This prevents subscription removal from
        // accidentally locking out existing users.
        return ! PricingRule::query()->where('key','feature.'.$feature)->where('enabled',true)
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))->exists();
    }

    public function grant(User $user,string $feature,string $accessType='granted',?int $units=null,?\DateTimeInterface $expiresAt=null,array $metadata=[]): FeatureEntitlement
    {
        return FeatureEntitlement::create(['user_id'=>$user->id,'feature'=>$feature,'access_type'=>$accessType,'remaining_units'=>$units,'status'=>'active','starts_at'=>now(),'expires_at'=>$expiresAt,'metadata'=>$metadata]);
    }
}
