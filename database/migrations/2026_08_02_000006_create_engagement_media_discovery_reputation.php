<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('attachable_type', 120)->nullable();
                $table->unsignedBigInteger('attachable_id')->nullable();
                $table->string('disk', 50)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 150);
                $table->string('extension', 30)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('sha256', 64)->nullable()->index();
                $table->string('visibility', 30)->default('private');
                $table->string('scan_status', 30)->default('pending');
                $table->string('scan_provider', 80)->nullable();
                $table->json('scan_result')->nullable();
                $table->string('preview_status', 30)->default('pending');
                $table->json('preview_metadata')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('scanned_at')->nullable();
                $table->timestamp('quarantined_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['attachable_type', 'attachable_id']);
                $table->index(['university_id', 'visibility', 'scan_status']);
            });
        }

        if (! Schema::hasTable('media_access_logs')) {
            Schema::create('media_access_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['media_asset_id', 'action', 'created_at']);
            });
        }

        if (! Schema::hasTable('engagement_threads')) {
            Schema::create('engagement_threads', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('target_type', 120);
                $table->unsignedBigInteger('target_id');
                $table->string('title')->nullable();
                $table->string('visibility', 30)->default('private');
                $table->string('status', 30)->default('open');
                $table->boolean('is_locked')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->unique(['target_type', 'target_id'], 'engagement_threads_target_unique');
                $table->index(['university_id', 'visibility', 'status']);
            });
        }

        if (! Schema::hasTable('engagement_comments')) {
            Schema::create('engagement_comments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('engagement_thread_id')->constrained('engagement_threads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('engagement_comments')->nullOnDelete();
                $table->string('comment_type', 50)->default('comment');
                $table->string('section_key', 150)->nullable();
                $table->text('body');
                $table->string('status', 30)->default('visible');
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_verified_response')->default(false);
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['engagement_thread_id', 'parent_id', 'status']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('engagement_reactions')) {
            Schema::create('engagement_reactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reactable_type', 120);
                $table->unsignedBigInteger('reactable_id');
                $table->string('reaction', 30)->default('like');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['user_id', 'reactable_type', 'reactable_id', 'reaction'], 'engagement_reactions_unique');
                $table->index(['reactable_type', 'reactable_id', 'reaction'], 'engagement_reactions_target');
            });
        }

        if (! Schema::hasTable('engagement_mentions')) {
            Schema::create('engagement_mentions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('mentioned_by')->constrained('users')->cascadeOnDelete();
                $table->string('source_type', 120);
                $table->unsignedBigInteger('source_id');
                $table->string('context')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['mentioned_user_id', 'source_type', 'source_id'], 'engagement_mentions_unique');
                $table->index(['mentioned_user_id', 'read_at', 'created_at']);
            });
        }

        if (! Schema::hasTable('engagement_reports')) {
            Schema::create('engagement_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->string('reportable_type', 120);
                $table->unsignedBigInteger('reportable_id');
                $table->string('reason', 80);
                $table->text('details')->nullable();
                $table->string('status', 30)->default('open');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['reportable_type', 'reportable_id', 'status'], 'engagement_reports_target');
                $table->index(['university_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('engagement_shares')) {
            Schema::create('engagement_shares', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('shareable_type', 120);
                $table->unsignedBigInteger('shareable_id');
                $table->string('channel', 50)->default('copy_link');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['shareable_type', 'shareable_id', 'created_at'], 'engagement_shares_target');
            });
        }

        if (! Schema::hasTable('engagement_subscriptions')) {
            Schema::create('engagement_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('subscribable_type', 120);
                $table->unsignedBigInteger('subscribable_id');
                $table->string('frequency', 30)->default('immediate');
                $table->boolean('is_muted')->default(false);
                $table->json('preferences')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'subscribable_type', 'subscribable_id'], 'engagement_subscriptions_unique');
            });
        }

        if (! Schema::hasTable('search_documents')) {
            Schema::create('search_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('searchable_type', 120);
                $table->unsignedBigInteger('searchable_id');
                $table->string('content_type', 80);
                $table->string('title');
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->text('keywords')->nullable();
                $table->string('visibility', 30)->default('private');
                $table->string('access_type', 30)->default('free');
                $table->json('embedding')->nullable();
                $table->unsignedSmallInteger('embedding_dimensions')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('indexed_at')->nullable();
                $table->timestamps();
                $table->unique(['searchable_type', 'searchable_id'], 'search_documents_searchable_unique');
                $table->index(['university_id', 'content_type', 'visibility']);
                $table->index(['access_type', 'indexed_at']);
            });
        }

        if (! Schema::hasTable('search_chunks')) {
            Schema::create('search_chunks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('search_document_id')->constrained('search_documents')->cascadeOnDelete();
                $table->unsignedInteger('position');
                $table->string('heading')->nullable();
                $table->longText('content');
                $table->unsignedInteger('token_count')->default(0);
                $table->json('embedding')->nullable();
                $table->string('checksum', 64);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['search_document_id', 'position']);
                $table->index(['search_document_id', 'checksum']);
            });
        }

        if (! Schema::hasTable('discovery_events')) {
            Schema::create('discovery_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('target_type', 120);
                $table->unsignedBigInteger('target_id');
                $table->string('event_type', 40);
                $table->decimal('weight', 8, 3)->default(1);
                $table->string('privacy_scope', 30)->default('private');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'event_type', 'created_at']);
                $table->index(['target_type', 'target_id', 'created_at'], 'discovery_events_target');
            });
        }

        if (! Schema::hasTable('recommendations')) {
            Schema::create('recommendations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('target_type', 120);
                $table->unsignedBigInteger('target_id');
                $table->decimal('score', 10, 4);
                $table->string('reason', 180)->nullable();
                $table->json('signals')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'target_type', 'target_id'], 'recommendations_unique');
                $table->index(['user_id', 'score', 'expires_at']);
            });
        }

        if (! Schema::hasTable('reputation_profiles')) {
            Schema::create('reputation_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->decimal('knowledge_score', 12, 2)->default(0);
                $table->decimal('quality_score', 12, 2)->default(0);
                $table->decimal('research_impact_score', 12, 2)->default(0);
                $table->decimal('community_score', 12, 2)->default(0);
                $table->decimal('overall_score', 12, 2)->default(0);
                $table->string('level_key', 80)->default('new_contributor');
                $table->unsignedInteger('publication_count')->default(0);
                $table->unsignedInteger('citation_count')->default(0);
                $table->unsignedInteger('follower_count')->default(0);
                $table->json('breakdown')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
                $table->index(['university_id', 'overall_score']);
            });
        }

        if (! Schema::hasTable('reputation_events')) {
            Schema::create('reputation_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('event_type', 80);
                $table->decimal('points', 10, 2);
                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['user_id', 'event_type', 'source_type', 'source_id'], 'reputation_events_source_unique');
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('achievements')) {
            Schema::create('achievements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('key', 100);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->string('category', 80)->default('contribution');
                $table->json('criteria');
                $table->decimal('points', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['university_id', 'key']);
            });
        }

        if (! Schema::hasTable('achievement_user')) {
            Schema::create('achievement_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->json('evidence')->nullable();
                $table->timestamp('awarded_at')->useCurrent();
                $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unique(['achievement_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('impact_snapshots')) {
            Schema::create('impact_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->string('subject_type', 120);
                $table->unsignedBigInteger('subject_id');
                $table->date('snapshot_date');
                $table->json('metrics');
                $table->decimal('impact_score', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['subject_type', 'subject_id', 'snapshot_date'], 'impact_snapshots_unique');
                $table->index(['university_id', 'snapshot_date', 'impact_score']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_snapshots');
        Schema::dropIfExists('achievement_user');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('reputation_events');
        Schema::dropIfExists('reputation_profiles');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('discovery_events');
        Schema::dropIfExists('search_chunks');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('engagement_subscriptions');
        Schema::dropIfExists('engagement_shares');
        Schema::dropIfExists('engagement_reports');
        Schema::dropIfExists('engagement_mentions');
        Schema::dropIfExists('engagement_reactions');
        Schema::dropIfExists('engagement_comments');
        Schema::dropIfExists('engagement_threads');
        Schema::dropIfExists('media_access_logs');
        Schema::dropIfExists('media_assets');
    }
};
