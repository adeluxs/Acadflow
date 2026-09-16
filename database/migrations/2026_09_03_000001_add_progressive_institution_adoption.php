<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('universities', function (Blueprint $table): void {
            if (! Schema::hasColumn('universities', 'adoption_status')) $table->string('adoption_status', 30)->default('unclaimed')->index();
            if (! Schema::hasColumn('universities', 'claimed_at')) $table->timestamp('claimed_at')->nullable();
            if (! Schema::hasColumn('universities', 'adopted_at')) $table->timestamp('adopted_at')->nullable();
        });

        Schema::table('departments', function (Blueprint $table): void {
            if (! Schema::hasColumn('departments', 'adoption_status')) $table->string('adoption_status', 30)->default('unclaimed')->index();
            if (! Schema::hasColumn('departments', 'adopted_at')) $table->timestamp('adopted_at')->nullable();
        });

        Schema::table('institution_onboarding_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('institution_onboarding_requests', 'request_type')) $table->string('request_type', 30)->default('create_new')->index();
        });

        Schema::create('user_institution_affiliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('affiliation_type', 40);
            $table->string('status', 30)->default('self_declared');
            $table->string('verification_method', 50)->nullable();
            $table->string('source', 50)->default('onboarding');
            $table->string('identifier_hash', 64)->nullable();
            $table->string('identifier_last4', 8)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id','university_id','affiliation_type'], 'user_institution_affiliation_unique');
            $table->index(['university_id','department_id','status'], 'affiliation_review_scope_idx');
        });

        Schema::create('department_adoption_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('department_name')->nullable();
            $table->string('department_code', 30)->nullable();
            $table->string('representative_title', 150);
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['university_id','status']);
            $table->index(['department_id','status']);
        });

        Schema::create('institution_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('requested_role', 40);
            $table->string('status', 30)->default('pending');
            $table->string('verification_method', 50)->nullable();
            $table->string('identifier_hash', 64)->nullable();
            $table->string('identifier_last4', 8)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['university_id','department_id','status'], 'institution_access_review_idx');
        });

        Schema::create('institution_email_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('status', 20)->default('proposed')->index();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_email_domains');
        Schema::dropIfExists('institution_access_requests');
        Schema::dropIfExists('department_adoption_requests');
        Schema::dropIfExists('user_institution_affiliations');
        Schema::table('institution_onboarding_requests', fn (Blueprint $table) => $table->dropColumn('request_type'));
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['adoption_status','adopted_at']));
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn(['adoption_status','claimed_at','adopted_at']));
    }
};
