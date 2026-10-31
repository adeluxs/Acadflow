<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ambassador_applications', function (Blueprint $table) {
            $table->string('browser_hash', 64)->nullable()->index();
        });
        Schema::table('ambassador_referrals', function (Blueprint $table) {
            $table->string('qualification_status', 20)->default('pending');
            $table->string('browser_hash', 64)->nullable()->index();
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->index(['ip_hash', 'registered_at'], 'amb_ref_ip_time');
            $table->index(['qualification_status', 'ambassador_application_id'], 'amb_ref_review_queue');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('review_signature', 64)->nullable();
        });
        Schema::create('ambassador_referral_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_referral_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamp('occurred_at');
            $table->string('kind', 30);
            $table->string('content_hash', 64);
            $table->unique(['ambassador_referral_id', 'activity_date'], 'amb_ref_unique_day');
            $table->unique(['ambassador_referral_id', 'content_hash'], 'amb_ref_unique_content');
        });
        Schema::table('ambassador_rewards', function (Blueprint $table) {
            // Keep the evidence even after cancellation; the application lock serializes claims.
            $table->foreignId('referral_id')->nullable()->constrained('ambassador_referrals')->restrictOnDelete();
            $table->index(['referral_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ambassador_rewards', function (Blueprint $table) {
            $table->dropIndex(['referral_id', 'status']);
            $table->dropConstrainedForeignId('referral_id');
        });
        Schema::dropIfExists('ambassador_referral_days');
        Schema::table('ambassador_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex('amb_ref_ip_time');
            $table->dropIndex('amb_ref_review_queue');
            $table->dropIndex(['browser_hash']);
            $table->dropIndex(['email_hash']);
            $table->dropColumn(['qualification_status', 'browser_hash', 'email_hash', 'ip_hash', 'reviewed_at', 'review_note', 'review_signature']);
        });
        Schema::table('ambassador_applications', function (Blueprint $table) {
            $table->dropIndex(['browser_hash']);
            $table->dropColumn('browser_hash');
        });
    }
};
