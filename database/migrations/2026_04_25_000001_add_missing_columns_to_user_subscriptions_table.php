<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Add amount column for subscription cost
            $table->decimal('amount', 10, 2)->nullable()->after('plan_id');
            
            // Add currency column
            $table->string('currency', 3)->default('USD')->after('amount');
            
            // Add payment status
            $table->string('payment_status')->default('pending')->after('trial_ends_at');
            
            // Add payment reference/transaction ID
            $table->string('payment_reference')->nullable()->after('payment_status');
            
            // Add payment gateway
            $table->string('gateway')->nullable()->after('payment_reference');
            
            // Add billing period columns for clarity
            $table->enum('billing_cycle', ['monthly', 'semester', 'yearly'])->default('monthly')->after('gateway');
            
            // Add auto-renewal flag
            $table->boolean('auto_renew')->default(true)->after('billing_cycle');
            
            // Index for payment status queries
            $table->index('payment_status');
            $table->index('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['gateway']);
            $table->dropColumn(['amount', 'currency', 'payment_status', 'payment_reference', 'gateway', 'billing_cycle', 'auto_renew']);
        });
    }
};
