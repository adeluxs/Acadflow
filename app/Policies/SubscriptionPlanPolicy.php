<?php

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        // Prevent deletion of default/free plan
        if ($plan->name === 'free' || $plan->price_per_month == 0) {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
