<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UniversitySeeder::class,
            SettingsSeeder::class,
            FeatureFlagSeeder::class,
            SubscriptionSeeder::class,
            CouponSeeder::class,
        ]);
    }
}
