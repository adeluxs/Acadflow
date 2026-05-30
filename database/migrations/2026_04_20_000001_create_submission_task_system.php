<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Submission Tasks - Lecturer-defined assignments
        if (! Schema::hasTable('submission_tasks')) {
            Schema::create('submission_tasks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // Lecturer who created

                // Basic Information
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('instructions')->nullable();
                $table->enum('type', ['assignment', 'project', 'siwes', 'group', 'seminar'])->default('assignment');

                // Visibility & Availability
                $table->timestamp('open_at')->nullable(); // When students can start submitting
                $table->timestamp('close_at')->nullable(); // When submissions normally close
                $table->timestamp('due_date')->nullable(); // Soft deadline (for display)
                $table->timestamp('late_deadline')->nullable(); // Hard deadline (last moment to submit)

                // Submission Rules
                $table->boolean('allow_late_submissions')->default(false); // Can submit after close_at
                $table->integer('max_resubmissions')->nullable(); // Null = unlimited
                $table->boolean('allow_group_submissions')->default(false);
                $table->integer('min_group_size')->default(1);
                $table->integer('max_group_size')->default(1);

                // File Requirements
                $table->json('allowed_file_types')->nullable(); // ['pdf', 'docx', 'doc', 'txt', 'png', 'jpg', 'jpeg']
                $table->integer('max_file_size_mb')->default(50); // Per file
                $table->integer('max_file_count')->default(10); // Total files per submission
                $table->integer('min_file_count')->default(1);

                // Grading
                $table->foreignId('rubric_id')->nullable()->constrained('submission_rubrics')->nullOnDelete();
                $table->decimal('max_score', 5, 2)->default(100);
                $table->boolean('require_approval_before_grading')->default(true);

                // Status & Publishing
                $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft');
                $table->boolean('is_visible_to_students')->default(true);

                // Additional Metadata
                $table->string('submission_format')->default('file'); // 'file', 'text', 'both'
                $table->integer('max_submissions_per_student')->default(1); // How many separate submissions can each student make
                $table->text('submission_requirements_json')->nullable(); // For complex requirements
                $table->decimal('late_submission_penalty_percent')->nullable(); // e.g., 10 for 10% deduction

                // Soft Delete & Timestamps
                $table->timestamps();
                $table->softDeletes();

                // Indexes for common queries
                $table->index(['course_id', 'semester_id']);
                $table->index(['open_at', 'close_at']);
                $table->index(['status', 'is_visible_to_students']);
            });
        }

        // Submission Requirements - More granular control
        if (! Schema::hasTable('submission_task_requirements')) {
            Schema::create('submission_task_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_task_id')->constrained('submission_tasks')->cascadeOnDelete();

                $table->string('requirement_type'); // 'file_type', 'max_size', 'word_count', 'structure', etc.
                $table->string('name'); // Display name
                $table->text('description')->nullable();
                $table->json('constraints'); // Flexible JSON for different requirement types
                $table->boolean('is_mandatory')->default(true);

                $table->timestamps();

                $table->index(['submission_task_id', 'requirement_type'], 'idx_submission_req_type');
            });
        }

        // Extend submissions table with task reference and metadata
        Schema::table('submissions', function (Blueprint $table) {
            // Add missing columns
            if (! Schema::hasColumn('submissions', 'submission_task_id')) {
                $table->foreignId('submission_task_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('submission_tasks')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('submissions', 'open_at')) {
                $table->timestamp('open_at')->nullable()->after('due_date'); // When assignment was made available to student
            }

            if (! Schema::hasColumn('submissions', 'close_at')) {
                $table->timestamp('close_at')->nullable()->after('open_at'); // When assignment closes
            }

            if (! Schema::hasColumn('submissions', 'is_late')) {
                $table->boolean('is_late')->default(false)->after('close_at'); // Denormalized for performance
            }

            if (! Schema::hasColumn('submissions', 'extension_until')) {
                $table->timestamp('extension_until')
                    ->nullable()
                    ->after('is_late'); // Individual extension for this student
            }

            if (! Schema::hasColumn('submissions', 'resubmission_count')) {
                $table->integer('resubmission_count')->default(0)->after('version'); // Track resubmissions
            }

            if (! Schema::hasColumn('submissions', 'last_resubmitted_at')) {
                $table->timestamp('last_resubmitted_at')
                    ->nullable()
                    ->after('resubmission_count'); // When was last resubmitted
            }

            if (! Schema::hasColumn('submissions', 'instructions_acknowledged_at')) {
                $table->timestamp('instructions_acknowledged_at')
                    ->nullable()
                    ->after('last_resubmitted_at'); // When student acknowledged requirements
            }
        });

        // Create a pivot table for submission task attachments (templates, guides, etc.)
        if (! Schema::hasTable('submission_task_attachments')) {
            Schema::create('submission_task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_task_id')->constrained('submission_tasks')->cascadeOnDelete();

                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->default('application/octet-stream');
                $table->bigInteger('file_size')->default(0);
                $table->string('description')->nullable();
                $table->enum('type', ['template', 'guide', 'rubric', 'example', 'other'])->default('other');
                $table->boolean('is_required')->default(false);

                $table->timestamps();
            });
        }

        // Submission Extensions - For tracking deadline extensions granted to students
        if (! Schema::hasTable('submission_extensions')) {
            Schema::create('submission_extensions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_task_id')->constrained('submission_tasks')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete(); // Lecturer who granted

                $table->timestamp('original_deadline')->nullable();
                $table->timestamp('extended_deadline')->nullable();
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');

                $table->timestamps();

                $table->unique(['submission_task_id', 'student_id']);
                $table->index(['original_deadline', 'extended_deadline']);
            });
        }

        // Late Submission Records - For audit trail
        if (! Schema::hasTable('late_submissions')) {
            Schema::create('late_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('submission_task_id')->constrained('submission_tasks')->cascadeOnDelete();

                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('deadline_at')->useCurrent();
                $table->integer('minutes_late');
                $table->decimal('penalty_applied_percent', 5, 2)->nullable();
                $table->decimal('score_before_penalty', 5, 2)->nullable();
                $table->decimal('score_after_penalty', 5, 2)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('late_submissions');
        Schema::dropIfExists('submission_extensions');
        Schema::dropIfExists('submission_task_attachments');
        Schema::dropIfExists('submission_task_requirements');
        Schema::dropIfExists('submission_tasks');

        // Drop columns from submissions if they exist
        Schema::table('submissions', function (Blueprint $table) {
            if (Schema::hasColumn('submissions', 'submission_task_id')) {
                $table->dropForeignKeyIfExists('submissions_submission_task_id_foreign');
                $table->dropColumn('submission_task_id');
            }

            foreach (['open_at', 'close_at', 'is_late', 'extension_until', 'resubmission_count',
                'last_resubmitted_at', 'instructions_acknowledged_at'] as $col) {
                if (Schema::hasColumn('submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
