<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plagiarism_checks')) {
            Schema::create('plagiarism_checks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject_type', 120);
                $table->unsignedBigInteger('subject_id');
                $table->string('provider', 80)->default('internal');
                $table->string('status', 30)->default('queued');
                $table->decimal('similarity_score', 5, 2)->nullable();
                $table->string('risk_level', 30)->nullable();
                $table->text('summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id', 'created_at'], 'plagiarism_checks_subject');
                $table->index(['university_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('plagiarism_matches')) {
            Schema::create('plagiarism_matches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plagiarism_check_id')->constrained('plagiarism_checks')->cascadeOnDelete();
                $table->string('source_type', 80);
                $table->string('source_identifier')->nullable();
                $table->string('source_title')->nullable();
                $table->text('source_url')->nullable();
                $table->string('source_hash', 64)->nullable();
                $table->text('source_excerpt')->nullable();
                $table->json('target_locations')->nullable();
                $table->decimal('similarity_score', 5, 2);
                $table->string('citation_status', 40)->default('unknown');
                $table->string('provider', 80)->default('internal');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['plagiarism_check_id', 'similarity_score']);
                $table->index(['source_type', 'source_identifier']);
            });
        }

        if (! Schema::hasTable('ai_grounding_sessions')) {
            Schema::create('ai_grounding_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('feature', 100);
                $table->string('subject_type', 120)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->text('question')->nullable();
                $table->longText('answer')->nullable();
                $table->string('status', 30)->default('processing');
                $table->string('provider', 80)->nullable();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->boolean('human_review_required')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'feature', 'created_at']);
                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! Schema::hasTable('ai_grounding_sources')) {
            Schema::create('ai_grounding_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ai_grounding_session_id')->constrained('ai_grounding_sessions')->cascadeOnDelete();
                $table->foreignId('search_document_id')->nullable()->constrained('search_documents')->nullOnDelete();
                $table->foreignId('search_chunk_id')->nullable()->constrained('search_chunks')->nullOnDelete();
                $table->string('source_type', 120);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('title')->nullable();
                $table->text('locator')->nullable();
                $table->text('excerpt')->nullable();
                $table->decimal('relevance_score', 8, 4)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['ai_grounding_session_id', 'relevance_score'], 'ai_grounding_session_relevance_idx');
            });
        }

        if (! Schema::hasTable('scholarly_integrations')) {
            Schema::create('scholarly_integrations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('provider', 80);
                $table->string('name');
                $table->boolean('is_active')->default(false);
                $table->boolean('is_default')->default(false);
                $table->json('credentials')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->string('health_status', 30)->default('unknown');
                $table->timestamps();
                $table->unique(['university_id', 'provider']);
            });
        }

        if (! Schema::hasTable('scholarly_records')) {
            Schema::create('scholarly_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('provider', 80);
                $table->string('external_identifier');
                $table->string('record_type', 50)->default('work');
                $table->string('title');
                $table->json('authors')->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->string('doi')->nullable();
                $table->string('orcid')->nullable();
                $table->text('url')->nullable();
                $table->longText('abstract')->nullable();
                $table->json('concepts')->nullable();
                $table->json('raw_data')->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'external_identifier']);
                $table->index(['doi', 'publication_year']);
            });
        }

        if (! Schema::hasTable('ai_prompt_versions')) {
            Schema::create('ai_prompt_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('feature', 100);
                $table->unsignedInteger('version');
                $table->longText('system_prompt')->nullable();
                $table->longText('user_template')->nullable();
                $table->json('response_schema')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['university_id', 'feature', 'version'], 'ai_prompt_versions_unique');
                $table->index(['feature', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('scholarly_records');
        Schema::dropIfExists('scholarly_integrations');
        Schema::dropIfExists('ai_grounding_sources');
        Schema::dropIfExists('ai_grounding_sessions');
        Schema::dropIfExists('plagiarism_matches');
        Schema::dropIfExists('plagiarism_checks');
    }
};
