<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coupons - discount codes for subscriptions
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique(); // e.g., SAVE20
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', ['percentage', 'fixed'])->default('percentage');
                $table->decimal('value', 10, 2); // percentage (10 = 10%) or fixed amount
                $table->boolean('is_active')->default(true);
                $table->integer('max_uses')->nullable(); // null = unlimited
                $table->integer('used_count')->default(0);
                $table->date('start_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->json('applicable_plans')->nullable(); // which plans this applies to
                $table->timestamps();
                
                $table->index(['code', 'is_active']);
            });
        }
        
        // Coupon Redemptions - track coupon usage
        if (!Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('user_subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
                $table->decimal('discount_amount', 10, 2);
                $table->timestamps();
                
                $table->index(['user_id', 'coupon_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
