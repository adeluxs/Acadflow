<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_ambassador_referral')->default(false);
        });
        Schema::create('ambassador_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('referral_code', 24)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('institution', 200);
            $table->string('department', 200);
            $table->string('study_level', 80);
            $table->string('leadership_role', 30);
            $table->string('phone', 40);
            $table->unsignedInteger('community_size');
            $table->text('statement');
            $table->timestamp('terms_accepted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ambassador_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ambassador_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('registered_at');
            $table->timestamp('retention_starts_at');
            $table->timestamp('retention_ends_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retained_at')->nullable();
            $table->index(['ambassador_application_id', 'registered_at'], 'ambassador_referrals_app_registered');
        });

        Schema::create('ambassador_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ambassador_application_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_key');
            $table->string('kind', 30);
            $table->string('title', 160);
            $table->date('occurred_on');
            $table->unsignedInteger('participants')->default(0);
            $table->text('summary');
            $table->string('evidence_url', 1000)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['ambassador_application_id', 'submission_key'], 'ambassador_reports_submission_unique');
            $table->index(['ambassador_application_id', 'status'], 'ambassador_reports_app_status');
            $table->index('status');
        });

        Schema::create('ambassador_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ambassador_application_id')->constrained()->restrictOnDelete();
            $table->string('award_key', 80);
            $table->string('kind', 30);
            $table->string('title', 160);
            $table->text('reason');
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 20)->default('pending');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fulfilment_reference', 160)->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->unique(['ambassador_application_id', 'award_key'], 'ambassador_rewards_award_unique');
            $table->index(['ambassador_application_id', 'status'], 'ambassador_rewards_app_status');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_rewards');
        Schema::dropIfExists('ambassador_reports');
        Schema::dropIfExists('ambassador_referrals');
        Schema::dropIfExists('ambassador_applications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('has_ambassador_referral'));
    }
};
