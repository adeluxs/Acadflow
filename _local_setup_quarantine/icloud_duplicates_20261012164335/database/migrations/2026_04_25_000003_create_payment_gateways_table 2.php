<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment Gateways - manages payment gateway configurations
        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique(); // paystack, stripe, flutterwave, etc.
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(false);
                $table->boolean('is_test_mode')->default(true);
                $table->json('credentials')->nullable(); // encrypted keys/secrets
                $table->json('settings')->nullable(); // gateway-specific settings
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                
                $table->index(['code', 'is_active']);
            });
        }
        
        // Transactions - logs all payment attempts
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique()->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
                $table->morphs('transactionable'); // Can be subscription or invoice
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('USD');
                $table->enum('type', ['payment', 'refund', 'charge', 'adjustment'])->default('payment');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
                $table->string('gateway_transaction_id')->nullable(); // Gateway's reference
                $table->string('gateway_status')->nullable(); // Raw status from gateway
                $table->json('metadata')->nullable(); // Additional data
                $table->text('notes')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                
                $table->index(['user_id', 'status']);
                // Note: morph index is created automatically by morphs()
            });
        }
        
        // Subscription Transactions - links transactions to user subscriptions
        if (!Schema::hasTable('subscription_transactions')) {
            Schema::create('subscription_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
                $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
                $table->string('description')->nullable();
                $table->timestamps();
                
                // Use shorter index name to avoid MySQL limit
                $table->index(['user_subscription_id', 'transaction_id'], 'sub_trans_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('payment_gateways');
    }
};
