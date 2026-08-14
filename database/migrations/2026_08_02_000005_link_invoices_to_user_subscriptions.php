<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'user_subscription_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreignId('user_subscription_id')
                    ->nullable()
                    ->after('subscription_id')
                    ->constrained('user_subscriptions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'user_subscription_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_subscription_id');
            });
        }
    }
};
