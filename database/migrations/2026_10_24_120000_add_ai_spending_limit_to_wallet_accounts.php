<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_accounts') && ! Schema::hasColumn('wallet_accounts', 'ai_monthly_spending_limit_minor')) {
            Schema::table('wallet_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('ai_monthly_spending_limit_minor')->nullable()->after('recovery_debt_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_accounts') && Schema::hasColumn('wallet_accounts', 'ai_monthly_spending_limit_minor')) {
            Schema::table('wallet_accounts', function (Blueprint $table): void {
                $table->dropColumn('ai_monthly_spending_limit_minor');
            });
        }
    }
};
