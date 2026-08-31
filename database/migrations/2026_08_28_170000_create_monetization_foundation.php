<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_accounts')) {
            Schema::table('wallet_accounts', function (Blueprint $table): void {
                if (! Schema::hasColumn('wallet_accounts', 'spending_balance_minor')) $table->unsignedBigInteger('spending_balance_minor')->default(0)->after('currency');
                if (! Schema::hasColumn('wallet_accounts', 'pending_earnings_minor')) $table->unsignedBigInteger('pending_earnings_minor')->default(0)->after('spending_balance_minor');
                if (! Schema::hasColumn('wallet_accounts', 'available_earnings_minor')) $table->unsignedBigInteger('available_earnings_minor')->default(0)->after('pending_earnings_minor');
                if (! Schema::hasColumn('wallet_accounts', 'lifetime_earnings_minor')) $table->unsignedBigInteger('lifetime_earnings_minor')->default(0)->after('available_earnings_minor');
                if (! Schema::hasColumn('wallet_accounts', 'recovery_debt_minor')) $table->unsignedBigInteger('recovery_debt_minor')->default(0)->after('lifetime_earnings_minor');
            });
        }

        $this->addMinorColumn('wallet_ledger_entries', 'amount_minor', 'amount');
        $this->addMinorColumn('wallet_ledger_entries', 'balance_after_minor', 'balance_after');
        if (Schema::hasTable('wallet_ledger_entries') && ! Schema::hasColumn('wallet_ledger_entries', 'balance_bucket')) {
            Schema::table('wallet_ledger_entries', fn (Blueprint $table) => $table->string('balance_bucket', 30)->nullable()->after('direction'));
        }

        $this->addMinorColumn('transactions', 'amount_minor', 'amount');
        $this->addMinorColumn('invoices', 'amount_minor', 'amount');
        $this->addMinorColumn('payments', 'amount_minor', 'amount');
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'currency')) {
            Schema::table('invoices', fn (Blueprint $table): mixed => $table->string('currency', 3)->nullable()->after('amount_minor'));
        }
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', fn (Blueprint $table): mixed => $table->string('currency', 3)->nullable()->after('amount_minor'));
        }
        $this->addMinorColumn('commerce_orders', 'subtotal_minor', 'subtotal');
        $this->addMinorColumn('commerce_orders', 'discount_amount_minor', 'discount_amount');
        $this->addMinorColumn('commerce_orders', 'tax_amount_minor', 'tax_amount');
        $this->addMinorColumn('commerce_orders', 'total_amount_minor', 'total_amount');
        $this->addMinorColumn('commerce_order_items', 'unit_price_minor', 'unit_price');
        $this->addMinorColumn('commerce_order_items', 'total_price_minor', 'total_price');
        $this->addMinorColumn('commerce_revenue_allocations', 'amount_minor', 'amount');
        $this->addMinorColumn('commerce_refunds', 'amount_minor', 'amount');
        if (Schema::hasTable('commerce_refunds')) {
            Schema::table('commerce_refunds', function (Blueprint $table): void {
                if (! Schema::hasColumn('commerce_refunds', 'provider_status')) $table->string('provider_status', 40)->nullable()->after('gateway_refund_id');
                if (! Schema::hasColumn('commerce_refunds', 'provider_payload')) $table->json('provider_payload')->nullable()->after('provider_status');
                if (! Schema::hasColumn('commerce_refunds', 'processing_started_at')) $table->timestamp('processing_started_at')->nullable()->after('provider_payload');
                if (! Schema::hasColumn('commerce_refunds', 'provider_confirmed_at')) $table->timestamp('provider_confirmed_at')->nullable()->after('processing_started_at');
                if (! Schema::hasColumn('commerce_refunds', 'reconciliation_required')) $table->boolean('reconciliation_required')->default(false)->after('provider_confirmed_at');
            });
        }
        $this->addMinorColumn('withdrawal_requests', 'amount_minor', 'amount');
        $this->addMinorColumn('withdrawal_requests', 'fee_minor', 'fee');

        if (! Schema::hasTable('monetization_idempotency_keys')) {
            Schema::create('monetization_idempotency_keys', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('operation', 80);
                $table->string('idempotency_key', 120);
                $table->char('request_hash', 64)->nullable();
                $table->string('status', 30)->default('processing');
                $table->string('result_type', 120)->nullable();
                $table->unsignedBigInteger('result_id')->nullable();
                $table->json('response')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['operation', 'idempotency_key'], 'monetization_idempotency_unique');
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ledger_journals')) {
            Schema::create('ledger_journals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('reference', 160)->unique();
                $table->string('operation', 80);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('currency', 3)->default('NGN');
                $table->string('status', 30)->default('posted');
                $table->json('metadata')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->index(['operation', 'posted_at']);
            });
        }

        if (! Schema::hasTable('ledger_postings')) {
            Schema::create('ledger_postings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ledger_journal_id')->constrained('ledger_journals')->cascadeOnDelete();
                $table->foreignId('wallet_account_id')->nullable()->constrained('wallet_accounts')->nullOnDelete();
                $table->string('account_code', 100);
                $table->string('direction', 6);
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3)->default('NGN');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['account_code', 'created_at']);
                $table->index(['wallet_account_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('pricing_rules')) {
            Schema::create('pricing_rules', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('key', 120);
                $table->string('name');
                $table->string('scope_type', 40)->default('global');
                $table->unsignedBigInteger('scope_id')->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('supersedes_id')->nullable();
                $table->string('currency', 3)->default('NGN');
                $table->unsignedBigInteger('unit_amount_minor')->nullable();
                $table->unsignedInteger('percentage_basis_points')->nullable();
                $table->boolean('enabled')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->unique(['key', 'scope_type', 'scope_id', 'version'], 'pricing_rule_scope_version_unique');
                $table->index(['key', 'scope_type', 'scope_id', 'enabled'], 'pricing_rule_active_lookup');
                $table->index(['enabled', 'starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('feature_entitlements')) {
            Schema::create('feature_entitlements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('feature', 120);
                $table->string('access_type', 30)->default('granted');
                $table->unsignedBigInteger('remaining_units')->nullable();
                $table->string('status', 30)->default('active');
                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'feature', 'status']);
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('commercial_accounts')) {
            Schema::create('commercial_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->unique()->constrained('universities')->nullOnDelete();
                $table->string('name');
                $table->string('currency', 3)->default('NGN');
                $table->unsignedBigInteger('prepaid_balance_minor')->default(0);
                $table->string('status', 30)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_funding_requests')) {
            Schema::create('wallet_funding_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3)->default('NGN');
                $table->string('status', 30)->default('pending');
                $table->string('gateway_reference')->nullable()->unique();
                $table->string('idempotency_key', 120)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_usage_charges')) {
            Schema::create('ai_usage_charges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('request_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('feature', 120);
                $table->string('provider', 50)->nullable();
                $table->string('model')->nullable();
                $table->unsignedBigInteger('input_tokens')->default(0);
                $table->unsignedBigInteger('output_tokens')->default(0);
                $table->unsignedBigInteger('provider_cost_micro_usd')->default(0);
                $table->unsignedBigInteger('user_charge_minor')->default(0);
                $table->bigInteger('platform_margin_minor')->default(0);
                $table->string('currency', 3)->default('NGN');
                $table->string('status', 30)->default('completed');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['provider', 'model', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_charges');
        Schema::dropIfExists('wallet_funding_requests');
        Schema::dropIfExists('commercial_accounts');
        Schema::dropIfExists('feature_entitlements');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('ledger_postings');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('monetization_idempotency_keys');
    }

    private function addMinorColumn(string $table, string $column, string $after): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) return;
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger($column)->nullable()->after($after));
    }
};
