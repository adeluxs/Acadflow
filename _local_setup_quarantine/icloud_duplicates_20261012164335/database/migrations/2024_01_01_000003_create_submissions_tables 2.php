<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Groups (must be before submissions due to FK)
        if (!Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('leader_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['forming', 'complete', 'archived'])->default('forming');
                $table->boolean('is_locked')->default(false);
                $table->integer('max_members')->default(6);
                $table->timestamp('formed_at')->nullable();
                $table->timestamps();
            });
        }

        // Submission Rubrics (no dependencies on later tables)
        if (!Schema::hasTable('submission_rubrics')) {
            Schema::create('submission_rubrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('criteria');
                $table->integer('total_points');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Submissions (groups now exists)
        if (!Schema::hasTable('submissions')) {
            Schema::create('submissions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
                $table->enum('type', ['assignment', 'project', 'siwes', 'group', 'seminar']);
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'submitted', 'under_review', 'correction_requested', 'resubmitted', 'approved', 'graded', 'archived'])->default('draft');
                $table->integer('version')->default(1);
                $table->timestamp('due_date')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('graded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Submission Versions
        if (!Schema::hasTable('submission_versions')) {
            Schema::create('submission_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->integer('version_number');
                $table->string('file_name');
                $table->string('file_path');
                $table->bigInteger('file_size');
                $table->string('mime_type', 100);
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_current')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Submission Comments
        if (!Schema::hasTable('submission_comments')) {
            Schema::create('submission_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('submission_comments')->nullOnDelete();
                $table->foreignId('version_id')->nullable()->constrained('submission_versions')->nullOnDelete();
                $table->text('content');
                $table->enum('type', ['general', 'correction', 'suggestion'])->default('general');
                $table->enum('status', ['pending', 'addressed', 'resolved'])->default('pending');
                $table->integer('page_number')->nullable();
                $table->float('x_position')->nullable();
                $table->float('y_position')->nullable();
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }

        // Submission Grades
        if (!Schema::hasTable('submission_grades')) {
            Schema::create('submission_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('score', 5, 2)->nullable();
                $table->decimal('max_score', 5, 2)->default(100);
                $table->foreignId('rubric_id')->nullable()->constrained('submission_rubrics')->nullOnDelete();
                $table->text('feedback')->nullable();
                $table->boolean('is_final')->default(true);
                $table->timestamps();
            });
        }

        // Group Members
        if (!Schema::hasTable('group_members')) {
            Schema::create('group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('role', ['leader', 'member'])->default('member');
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['group_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('submission_grades');
        Schema::dropIfExists('submission_comments');
        Schema::dropIfExists('submission_versions');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('submission_rubrics');
        Schema::dropIfExists('groups');
    }
};
