<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new fields to subscription_plans for better B2B/B2C management
        if (Schema::hasTable('subscription_plans')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                // B2B/B2C classification
                $table->enum('plan_type', ['b2c', 'b2b', 'free'])->default('b2c')->after('name');
                
                // Trial settings
                $table->integer('trial_days')->default(0)->after('plan_type');
                $table->boolean('has_trial')->default(false)->after('trial_days');
                
                // Billing cycles - more flexible
                $table->json('available_billing_cycles')->nullable()->after('billing_cycle');
                
                // Recommended badge
                $table->boolean('is_recommended')->default(false)->after('allow_white_label');
                
                // B2B specific fields
                $table->integer('min_seats')->default(1)->nullable()->after('is_recommended');
                $table->integer('max_seats')->default(1)->nullable()->after('min_seats');
                $table->boolean('priority_support')->default(false)->after('max_seats');
                $table->boolean('dedicated_account_manager')->default(false)->after('priority_support');
                $table->boolean('sso_enabled')->default(false)->after('dedicated_account_manager');
                $table->boolean('api_access')->default(false)->after('sso_enabled');
                $table->boolean('custom_branding')->default(false)->after('api_access');
                
                // Upgrade/downgrade settings
                $table->boolean('can_upgrade')->default(true)->after('custom_branding');
                $table->boolean('can_downgrade')->default(true)->after('can_upgrade');
                $table->boolean('prorated_upgrades')->default(true)->after('can_downgrade');
                
                // Cancellation policy
                $table->integer('cancellation_minimum_days')->default(0)->after('prorated_upgrades');
                $table->boolean('refundable')->default(false)->after('cancellation_minimum_days');
                $table->integer('refund_period_days')->default(0)->after('refundable');
                
                // Custom pricing
                $table->boolean('custom_pricing')->default(false)->after('refund_period_days');
                $table->text('pricing_note')->nullable()->after('custom_pricing');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_plans')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn([
                    'plan_type',
                    'trial_days',
                    'has_trial',
                    'available_billing_cycles',
                    'is_recommended',
                    'min_seats',
                    'max_seats',
                    'priority_support',
                    'dedicated_account_manager',
                    'sso_enabled',
                    'api_access',
                    'custom_branding',
                    'can_upgrade',
                    'can_downgrade',
                    'prorated_upgrades',
                    'cancellation_minimum_days',
                    'refundable',
                    'refund_period_days',
                    'custom_pricing',
                    'pricing_note',
                ]);
            });
        }
    }
};
