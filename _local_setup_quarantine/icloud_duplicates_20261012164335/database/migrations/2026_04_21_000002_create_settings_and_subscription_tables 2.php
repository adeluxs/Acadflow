<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // System Settings - key-value store for platform configuration
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string'); // string, integer, boolean, json, etc.
                $table->string('group')->default('general'); // general, academic, notification, subscription, security, pwa, storage
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false); // Can be accessed via API/public
                $table->timestamps();

                $table->index('key');
                $table->index('group');
            });
        }

        // Subscription Plans - tier definitions
        if (!Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // free, limited, lecturer, department
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->decimal('price_per_month', 10, 2)->nullable();
                $table->decimal('price_per_semester', 10, 2)->nullable();
                $table->decimal('price_per_year', 10, 2)->nullable();
                $table->enum('billing_cycle', ['monthly', 'semester', 'yearly'])->default('monthly');
                $table->json('features')->nullable(); // Array of enabled features
                $table->json('limits')->nullable(); // Storage, users, courses, etc.
                $table->integer('max_courses')->nullable();
                $table->integer('max_students_per_course')->nullable();
                $table->integer('max_file_upload_size_mb')->default(50);
                $table->integer('max_storage_gb')->nullable();
                $table->boolean('allow_group_submissions')->default(true);
                $table->boolean('allow_rubrics')->default(true);
                $table->boolean('allow_attendance_tracking')->default(true);
                $table->boolean('allow_document_generation')->default(false);
                $table->boolean('allow_api_access')->default(false);
                $table->boolean('allow_white_label')->default(false);
                $table->integer('max_administrators')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // User Subscriptions (link users to plans)
        if (!Schema::hasTable('user_subscriptions')) {
            Schema::create('user_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
                $table->string('status')->default('active'); // active, cancelled, expired, suspended
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->string('payment_method')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'plan_id']);
                $table->index(['user_id', 'status']);
            });
        }

        // Feature Flags - toggle features on/off system-wide
        if (!Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_enabled')->default(false);
                $table->text('description')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('settings');
    }
};
