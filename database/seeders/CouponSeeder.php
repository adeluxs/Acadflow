<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $basicPlan = SubscriptionPlan::where('name', 'basic')->first();
        $proPlan = SubscriptionPlan::where('name', 'pro')->first();

        $coupons = [
            [
                'code' => 'WELCOME20',
                'name' => 'Welcome Discount',
                'description' => '20% off your first month subscription',
                'type' => 'percentage',
                'value' => 20,
                'is_active' => true,
                'max_uses' => 100,
                'start_date' => now(),
                'expiry_date' => now()->addMonths(6),
                'applicable_plans' => $basicPlan ? [$basicPlan->id] : [],
            ],
            [
                'code' => 'PRO30',
                'name' => 'Pro Plan Discount',
                'description' => '30% off Pro plan for the first month',
                'type' => 'percentage',
                'value' => 30,
                'is_active' => true,
                'max_uses' => 50,
                'start_date' => now(),
                'expiry_date' => now()->addMonths(3),
                'applicable_plans' => $proPlan ? [$proPlan->id] : [],
            ],
            [
                'code' => 'SAVE10',
                'name' => 'Save $10',
                'description' => '$10 off any subscription plan',
                'type' => 'fixed',
                'value' => 10,
                'is_active' => true,
                'max_uses' => 200,
                'start_date' => now(),
                'expiry_date' => now()->addMonths(6),
                'applicable_plans' => [],
            ],
            [
                'code' => 'FREETRIAL',
                'name' => 'Extended Free Trial',
                'description' => 'Get an extra month free on any annual plan',
                'type' => 'percentage',
                'value' => 100,
                'is_active' => true,
                'max_uses' => 20,
                'start_date' => now(),
                'expiry_date' => now()->addMonths(2),
                'applicable_plans' => [],
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::firstOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
