<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_moderation_reports')) {
            Schema::create('knowledge_moderation_reports', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('plagiarism_check_id')->nullable()->constrained('plagiarism_checks')->nullOnDelete();
                $table->string('status',30)->default('queued'); $table->decimal('quality_score',5,2)->nullable();
                $table->decimal('similarity_score',5,2)->nullable(); $table->string('risk_level',30)->nullable();
                $table->json('findings')->nullable(); $table->text('summary')->nullable();
                $table->boolean('human_review_required')->default(true); $table->timestamp('completed_at')->nullable(); $table->timestamps();
                $table->index(['knowledge_publication_id','status','created_at'], 'knowledge_moderation_reports_scope');
            });
        }
        if (! Schema::hasTable('academic_challenge_votes')) {
            Schema::create('academic_challenge_votes', function (Blueprint $table): void {
                $table->id(); $table->foreignId('academic_challenge_entry_id')->constrained('academic_challenge_entries')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $table->timestamp('created_at')->useCurrent();
                $table->unique(['academic_challenge_entry_id', 'user_id'], 'challenge_votes_entry_user_uq');
            });
        }
        if (! Schema::hasTable('academic_certificates')) {
            Schema::create('academic_certificates', function (Blueprint $table): void {
                $table->id(); $table->uuid('uuid')->unique(); $table->string('verification_code',64)->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('certifiable_type',120); $table->unsignedBigInteger('certifiable_id');
                $table->string('title'); $table->string('issuer')->nullable(); $table->date('issued_on');
                $table->string('file_path')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
                $table->unique(['user_id','certifiable_type','certifiable_id'], 'academic_certificates_unique');
                $table->index(['certifiable_type','certifiable_id']);
            });
        }
        if (! Schema::hasTable('knowledge_poll_options')) {
            Schema::create('knowledge_poll_options', function (Blueprint $table): void {
                $table->id(); $table->foreignId('knowledge_community_post_id')->constrained('knowledge_community_posts')->cascadeOnDelete();
                $table->string('label'); $table->unsignedInteger('position')->default(0); $table->timestamps();
            });
        }
        if (! Schema::hasTable('knowledge_poll_votes')) {
            Schema::create('knowledge_poll_votes', function (Blueprint $table): void {
                $table->id(); $table->foreignId('knowledge_poll_option_id')->constrained('knowledge_poll_options')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $table->timestamp('created_at')->useCurrent();
                $table->unique(['knowledge_poll_option_id','user_id']);
            });
        }
        Schema::table('knowledge_publications', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_publications','moderation_report_id')) $table->foreignId('moderation_report_id')->nullable()->after('moderated_by')->constrained('knowledge_moderation_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('knowledge_publications','moderation_report_id')) Schema::table('knowledge_publications', fn (Blueprint $table) => $table->dropConstrainedForeignId('moderation_report_id'));
        Schema::dropIfExists('knowledge_poll_votes'); Schema::dropIfExists('knowledge_poll_options'); Schema::dropIfExists('academic_certificates'); Schema::dropIfExists('academic_challenge_votes'); Schema::dropIfExists('knowledge_moderation_reports');
    }
};
