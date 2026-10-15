<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_usage_charges')) {
            return;
        }

        Schema::create('feature_usage_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('request_id', 160)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->foreignId('commercial_account_id')->nullable()->constrained('commercial_accounts')->nullOnDelete();
            $table->foreignId('feature_entitlement_id')->nullable()->constrained('feature_entitlements')->nullOnDelete();
            $table->string('feature', 120);
            $table->string('pricing_type', 30);
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('funding_source', 40)->default('platform');
            $table->string('status', 30)->default('reserved');
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'feature', 'created_at']);
            $table->index(['university_id', 'feature', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usage_charges');
    }
};
