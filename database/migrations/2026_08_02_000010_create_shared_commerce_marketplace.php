<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_accounts')) {
            Schema::create('wallet_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('currency', 3)->default('NGN');
                $table->decimal('available_balance', 14, 2)->default(0);
                $table->decimal('pending_balance', 14, 2)->default(0);
                $table->decimal('lifetime_earnings', 14, 2)->default(0);
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_ledger_entries')) {
            Schema::create('wallet_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('entry_type', 40);
                $table->string('direction', 10);
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2);
                $table->string('reference_type', 120)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('status', 30)->default('posted');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->index(['wallet_account_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (! Schema::hasTable('commerce_orders')) {
            Schema::create('commerce_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->string('order_number')->unique();
                $table->string('currency', 3)->default('NGN');
                $table->decimal('subtotal', 14, 2);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2);
                $table->string('status', 30)->default('pending');
                $table->string('payment_status', 30)->default('unpaid');
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->json('billing_details')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['buyer_id', 'status', 'created_at']);
                $table->index(['university_id', 'payment_status']);
            });
        }

        if (! Schema::hasTable('commerce_order_items')) {
            Schema::create('commerce_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->string('purchasable_type', 120);
                $table->unsignedBigInteger('purchasable_id');
                $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('total_price', 14, 2);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['purchasable_type', 'purchasable_id']);
                $table->index(['seller_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('commerce_entitlements')) {
            Schema::create('commerce_entitlements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('commerce_order_item_id')->nullable()->constrained('commerce_order_items')->nullOnDelete();
                $table->string('entitled_type', 120);
                $table->unsignedBigInteger('entitled_id');
                $table->string('access_level', 30)->default('full');
                $table->string('status', 30)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'entitled_type', 'entitled_id'], 'commerce_entitlements_unique');
                $table->index(['user_id', 'status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('commerce_revenue_allocations')) {
            Schema::create('commerce_revenue_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commerce_order_item_id')->constrained('commerce_order_items')->cascadeOnDelete();
                $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('beneficiary_university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('allocation_type', 40);
                $table->decimal('percentage', 7, 4)->default(0);
                $table->decimal('amount', 14, 2);
                $table->string('status', 30)->default('pending');
                $table->timestamp('released_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['beneficiary_user_id', 'status']);
                $table->index(['beneficiary_university_id', 'status'], 'commerce_alloc_beneficiary_status_idx');
            });
        }

        if (! Schema::hasTable('commerce_refunds')) {
            Schema::create('commerce_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->text('reason');
                $table->string('status', 30)->default('requested');
                $table->string('gateway_refund_id')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['commerce_order_id', 'status']);
            });
        }

        if (! Schema::hasTable('payout_accounts')) {
            Schema::create('payout_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 50)->default('bank');
                $table->string('account_name');
                $table->string('account_number');
                $table->string('bank_code')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('currency', 3)->default('NGN');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
                $table->foreignId('payout_account_id')->constrained('payout_accounts')->restrictOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->decimal('fee', 14, 2)->default(0);
                $table->string('status', 30)->default('pending');
                $table->string('provider_reference')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['wallet_account_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('digital_resource_files')) {
            Schema::create('digital_resource_files', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->boolean('is_preview')->default(false);
                $table->unsignedInteger('download_limit')->nullable();
                $table->timestamps();
                $table->unique(['knowledge_publication_id', 'media_asset_id'], 'digital_resource_files_unique');
            });
        }

        if (! Schema::hasTable('secure_download_tokens')) {
            Schema::create('secure_download_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('token_hash', 64)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->foreignId('commerce_entitlement_id')->nullable()->constrained('commerce_entitlements')->nullOnDelete();
                $table->unsignedInteger('max_downloads')->default(1);
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_download_tokens');
        Schema::dropIfExists('digital_resource_files');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('commerce_refunds');
        Schema::dropIfExists('commerce_revenue_allocations');
        Schema::dropIfExists('commerce_entitlements');
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_accounts');
    }
};
