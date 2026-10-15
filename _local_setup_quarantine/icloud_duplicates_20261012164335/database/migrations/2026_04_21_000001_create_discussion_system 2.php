<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Course Discussions (Q&A threads)
        if (!Schema::hasTable('discussions')) {
            Schema::create('discussions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Person who asked
                $table->foreignId('material_id')->nullable()->constrained('course_materials')->nullOnDelete(); // Linked to material if applicable
                $table->string('title');
                $table->text('content');
                $table->enum('status', ['open', 'resolved', 'closed', 'archived'])->default('open');
                $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
                $table->boolean('is_pinned')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['course_id', 'status']);
                $table->index('is_pinned');
            });
        }

        // Discussion Replies (comments/answers)
        if (!Schema::hasTable('discussion_replies')) {
            Schema::create('discussion_replies', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_reply_id')->nullable()->constrained('discussion_replies')->nullOnDelete(); // For threaded replies
                $table->text('content');
                $table->enum('type', ['answer', 'comment', 'clarification'])->default('answer');
                $table->boolean('is_accepted')->default(false); // Accepted answer
                $table->integer('like_count')->default(0);
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['discussion_id', 'is_accepted']);
                $table->index('user_id');
            });
        }

        // Discussion Tags/Labels
        if (!Schema::hasTable('discussion_tags')) {
            Schema::create('discussion_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('color')->default('#6B7280'); // Tailwind gray
                $table->timestamps();
            });
        }

        // Discussion Tag pivot
        if (!Schema::hasTable('discussion_tag_discussion')) {
            Schema::create('discussion_tag_discussion', function (Blueprint $table) {
                $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
                $table->foreignId('discussion_tag_id')->constrained('discussion_tags')->cascadeOnDelete();
                $table->primary(['discussion_id', 'discussion_tag_id']);
            });
        }

        // Material access tracking (for recommendations)
        if (!Schema::hasTable('material_access_logs')) {
            Schema::create('material_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_id')->constrained('course_materials')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('action', ['viewed', 'downloaded', 'shared'])->default('viewed');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('accessed_at')->useCurrent();

                $table->index(['material_id', 'user_id']);
                $table->index('accessed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_access_logs');
        Schema::dropIfExists('discussion_tag_discussion');
        Schema::dropIfExists('discussion_tags');
        Schema::dropIfExists('discussion_replies');
        Schema::dropIfExists('discussions');
    }
};
