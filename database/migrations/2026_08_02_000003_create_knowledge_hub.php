<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_categories')) {
            Schema::create('knowledge_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug', 120);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['university_id', 'slug']);
            });
        }

        if (! Schema::hasTable('knowledge_tags')) {
            Schema::create('knowledge_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug', 120)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('knowledge_publications')) {
            Schema::create('knowledge_publications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('source_research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
                $table->foreignId('content_document_id')->constrained('content_documents')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('content_type', 100)->default('academic_article');
                $table->text('excerpt')->nullable();
                $table->string('status', 50)->default('draft');
                $table->string('visibility', 50)->default('institution');
                $table->string('access_type', 50)->default('free');
                $table->decimal('price', 12, 2)->default(0);
                $table->text('moderation_note')->nullable();
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('view_count')->default(0);
                $table->unsignedBigInteger('bookmark_count')->default(0);
                $table->unsignedBigInteger('comment_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'visibility', 'published_at']);
                $table->index(['university_id', 'department_id', 'status']);
                $table->index(['creator_id', 'status']);
            });
        }

        if (! Schema::hasTable('knowledge_publication_tag')) {
            Schema::create('knowledge_publication_tag', function (Blueprint $table) {
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('knowledge_tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['knowledge_publication_id', 'knowledge_tag_id'], 'knowledge_publication_tag_pk');
            });
        }

        if (! Schema::hasTable('knowledge_bookmarks')) {
            Schema::create('knowledge_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['knowledge_publication_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('knowledge_follows')) {
            Schema::create('knowledge_follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                $table->string('target_type', 50);
                $table->unsignedBigInteger('target_id');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['follower_id', 'target_type', 'target_id'], 'knowledge_follows_unique');
                $table->index(['target_type', 'target_id']);
            });
        }

        if (! Schema::hasTable('knowledge_events')) {
            Schema::create('knowledge_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('knowledge_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_type', 50);
                $table->decimal('value', 12, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['knowledge_publication_id', 'event_type', 'created_at'], 'knowledge_events_pub_type_time');
            });
        }

        if (! Schema::hasTable('knowledge_citations')) {
            Schema::create('knowledge_citations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('citing_publication_id')->constrained('knowledge_publications')->cascadeOnDelete();
                $table->foreignId('cited_publication_id')->nullable()->constrained('knowledge_publications')->nullOnDelete();
                $table->foreignId('academic_reference_id')->nullable()->constrained('academic_references')->nullOnDelete();
                $table->string('external_identifier')->nullable();
                $table->string('source', 50)->default('internal');
                $table->timestamps();
                $table->index(['citing_publication_id', 'cited_publication_id'], 'knowledge_citations_pub_pair_idx');
            });
        }

        if (! Schema::hasTable('academic_reference_links')) {
            Schema::create('academic_reference_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_reference_id')->constrained('academic_references')->cascadeOnDelete();
                $table->foreignId('content_document_id')->nullable()->constrained('content_documents')->cascadeOnDelete();
                $table->foreignId('research_project_id')->nullable()->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('knowledge_publication_id')->nullable()->constrained('knowledge_publications')->cascadeOnDelete();
                $table->string('purpose', 50)->default('citation');
                $table->timestamps();
                $table->index(['research_project_id', 'purpose']);
                $table->index(['knowledge_publication_id', 'purpose']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_reference_links');
        Schema::dropIfExists('knowledge_citations');
        Schema::dropIfExists('knowledge_events');
        Schema::dropIfExists('knowledge_follows');
        Schema::dropIfExists('knowledge_bookmarks');
        Schema::dropIfExists('knowledge_publication_tag');
        Schema::dropIfExists('knowledge_publications');
        Schema::dropIfExists('knowledge_tags');
        Schema::dropIfExists('knowledge_categories');
    }
};
