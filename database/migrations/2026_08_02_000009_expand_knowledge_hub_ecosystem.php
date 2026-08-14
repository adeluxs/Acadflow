<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('creator_profiles')) {
            Schema::create('creator_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('headline')->nullable();
                $table->text('biography')->nullable();
                $table->json('expertise')->nullable();
                $table->string('position')->nullable();
                $table->string('orcid', 30)->nullable()->unique();
                $table->text('website')->nullable();
                $table->json('social_links')->nullable();
                $table->string('verification_status', 30)->default('unverified');
                $table->json('privacy_settings')->nullable();
                $table->boolean('is_public')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('verification_requests')) {
            Schema::create('verification_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('verification_type', 60);
                $table->text('statement')->nullable();
                $table->json('evidence')->nullable();
                $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
                $table->string('status', 30)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['university_id', 'status', 'verification_type'], 'verification_tenant_status_type_idx');
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('knowledge_communities')) {
            Schema::create('knowledge_communities', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('community_type', 50)->default('interest');
                $table->string('visibility', 30)->default('public');
                $table->string('membership_mode', 30)->default('open');
                $table->string('status', 30)->default('active');
                $table->json('rules')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'department_id', 'status']);
            });
        }

        if (! Schema::hasTable('knowledge_community_members')) {
            Schema::create('knowledge_community_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('knowledge_community_id')->constrained('knowledge_communities')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('member');
                $table->string('status', 30)->default('active');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->unique(['knowledge_community_id', 'user_id'], 'knowledge_community_members_unique');
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('knowledge_community_posts')) {
            Schema::create('knowledge_community_posts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('knowledge_community_id')->constrained('knowledge_communities')->cascadeOnDelete();
                $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('content_document_id')->constrained('content_documents')->cascadeOnDelete();
                $table->string('post_type', 40)->default('discussion');
                $table->string('title')->nullable();
                $table->string('status', 30)->default('published');
                $table->boolean('is_pinned')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['knowledge_community_id', 'status', 'created_at'], 'knowledge_community_posts_scope');
            });
        }

        if (! Schema::hasTable('learning_paths')) {
            Schema::create('learning_paths', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('visibility', 30)->default('public');
                $table->string('access_type', 30)->default('free');
                $table->decimal('price', 12, 2)->default(0);
                $table->string('status', 30)->default('draft');
                $table->boolean('certificate_enabled')->default(false);
                $table->json('outcomes')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'status', 'visibility']);
            });
        }

        if (! Schema::hasTable('learning_path_items')) {
            Schema::create('learning_path_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
                $table->string('item_type', 120);
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('position');
                $table->boolean('is_required')->default(true);
                $table->unsignedInteger('estimated_minutes')->default(0);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->unique(['learning_path_id', 'position']);
            });
        }

        if (! Schema::hasTable('learning_enrollments')) {
            Schema::create('learning_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 30)->default('active');
                $table->decimal('progress', 5, 2)->default(0);
                $table->foreignId('current_item_id')->nullable()->constrained('learning_path_items')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['learning_path_id', 'user_id']);
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('learning_progress')) {
            Schema::create('learning_progress', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
                $table->foreignId('learning_path_item_id')->constrained('learning_path_items')->cascadeOnDelete();
                $table->string('status', 30)->default('not_started');
                $table->decimal('score', 5, 2)->nullable();
                $table->unsignedInteger('time_spent_seconds')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->json('state')->nullable();
                $table->timestamps();
                $table->unique(['learning_enrollment_id', 'learning_path_item_id'], 'learning_progress_unique');
            });
        }

        if (! Schema::hasTable('reading_lists')) {
            Schema::create('reading_lists', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('list_type', 30)->default('private');
                $table->string('visibility', 30)->default('private');
                $table->boolean('is_collaborative')->default(false);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['owner_id', 'visibility']);
            });
        }

        if (! Schema::hasTable('reading_list_items')) {
            Schema::create('reading_list_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reading_list_id')->constrained('reading_lists')->cascadeOnDelete();
                $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
                $table->string('item_type', 120);
                $table->unsignedBigInteger('item_id');
                $table->unsignedInteger('position')->default(0);
                $table->string('status', 30)->default('unread');
                $table->text('note')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['reading_list_id', 'item_type', 'item_id'], 'reading_list_items_unique');
            });
        }

        if (! Schema::hasTable('reading_list_members')) {
            Schema::create('reading_list_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reading_list_id')->constrained('reading_lists')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('viewer');
                $table->timestamps();
                $table->unique(['reading_list_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('academic_events')) {
            Schema::create('academic_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('knowledge_community_id')->nullable()->constrained('knowledge_communities')->nullOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('event_type', 50);
                $table->string('visibility', 30)->default('public');
                $table->string('status', 30)->default('draft');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('location')->nullable();
                $table->text('online_url')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('certificate_enabled')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'status', 'starts_at']);
            });
        }

        if (! Schema::hasTable('academic_event_registrations')) {
            Schema::create('academic_event_registrations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_event_id')->constrained('academic_events')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 30)->default('registered');
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('attended_at')->nullable();
                $table->string('certificate_path')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['academic_event_id', 'user_id'], 'academic_event_registrations_unique');
            });
        }

        if (! Schema::hasTable('academic_challenges')) {
            Schema::create('academic_challenges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('challenge_type', 50);
                $table->string('status', 30)->default('draft');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->json('rules')->nullable();
                $table->json('judging_criteria')->nullable();
                $table->json('rewards')->nullable();
                $table->boolean('public_voting_enabled')->default(false);
                $table->boolean('ai_assistance_enabled')->default(false);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'status', 'starts_at']);
            });
        }

        if (! Schema::hasTable('academic_challenge_entries')) {
            Schema::create('academic_challenge_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('academic_challenge_id')->constrained('academic_challenges')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('content_document_id')->nullable()->constrained('content_documents')->nullOnDelete();
                $table->foreignId('knowledge_publication_id')->nullable()->constrained('knowledge_publications')->nullOnDelete();
                $table->string('title');
                $table->string('status', 30)->default('submitted');
                $table->decimal('score', 8, 2)->nullable();
                $table->unsignedInteger('vote_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['academic_challenge_id', 'user_id'], 'academic_challenge_entries_unique');
            });
        }

        if (! Schema::hasTable('academic_challenge_scores')) {
            Schema::create('academic_challenge_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_challenge_entry_id')->constrained('academic_challenge_entries')->cascadeOnDelete();
                $table->foreignId('judge_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('criterion');
                $table->decimal('score', 8, 2);
                $table->text('feedback')->nullable();
                $table->boolean('is_ai_assisted')->default(false);
                $table->timestamps();
                $table->unique(['academic_challenge_entry_id', 'judge_id', 'criterion'], 'academic_challenge_scores_unique');
            });
        }

        if (! Schema::hasTable('external_citation_records')) {
            Schema::create('external_citation_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->string('provider', 80);
                $table->string('external_work_id');
                $table->string('citing_work_id')->nullable();
                $table->string('citing_title')->nullable();
                $table->text('citing_url')->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->json('provenance')->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
                $table->unique(['knowledge_publication_id', 'provider', 'external_work_id', 'citing_work_id'], 'external_citation_records_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_citation_records');
        Schema::dropIfExists('academic_challenge_scores');
        Schema::dropIfExists('academic_challenge_entries');
        Schema::dropIfExists('academic_challenges');
        Schema::dropIfExists('academic_event_registrations');
        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('reading_list_members');
        Schema::dropIfExists('reading_list_items');
        Schema::dropIfExists('reading_lists');
        Schema::dropIfExists('learning_progress');
        Schema::dropIfExists('learning_enrollments');
        Schema::dropIfExists('learning_path_items');
        Schema::dropIfExists('learning_paths');
        Schema::dropIfExists('knowledge_community_posts');
        Schema::dropIfExists('knowledge_community_members');
        Schema::dropIfExists('knowledge_communities');
        Schema::dropIfExists('verification_requests');
        Schema::dropIfExists('creator_profiles');
    }
};
