<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('siwes_placements')) {
            Schema::create('siwes_placements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->string('organization_name');
                $table->text('organization_address')->nullable();
                $table->string('industry_sector')->nullable();
                $table->string('industry_supervisor_name')->nullable();
                $table->string('industry_supervisor_email')->nullable();
                $table->string('industry_supervisor_phone')->nullable();
                $table->date('started_on')->nullable();
                $table->date('ended_on')->nullable();
                $table->unsignedInteger('required_hours')->default(0);
                $table->unsignedInteger('completed_hours')->default(0);
                $table->string('status', 30)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'submission_id']);
                $table->index(['student_id', 'status']);
            });
        }

        if (! Schema::hasTable('siwes_log_entries')) {
            Schema::create('siwes_log_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('siwes_placement_id')->constrained('siwes_placements')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->date('entry_date');
                $table->string('period_type', 20)->default('daily');
                $table->unsignedSmallInteger('hours')->default(0);
                $table->string('title');
                $table->longText('activities');
                $table->longText('skills_learned')->nullable();
                $table->longText('challenges')->nullable();
                $table->string('status', 30)->default('draft');
                $table->text('employer_comment')->nullable();
                $table->text('lecturer_comment')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['siwes_placement_id', 'entry_date', 'period_type'], 'siwes_log_placement_date_period_idx');
            });
        }

        if (! Schema::hasTable('siwes_attendance_records')) {
            Schema::create('siwes_attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('siwes_placement_id')->constrained('siwes_placements')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->time('check_in_at')->nullable();
                $table->time('check_out_at')->nullable();
                $table->decimal('hours_worked', 5, 2)->default(0);
                $table->string('status', 20)->default('present');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('verified_by_type', 30)->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['siwes_placement_id', 'attendance_date'], 'siwes_attendance_placement_date_uq');
            });
        }

        if (! Schema::hasTable('siwes_evaluations')) {
            Schema::create('siwes_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('siwes_placement_id')->constrained('siwes_placements')->cascadeOnDelete();
                $table->string('evaluator_type', 30);
                $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('attendance_score', 5, 2)->nullable();
                $table->decimal('technical_score', 5, 2)->nullable();
                $table->decimal('conduct_score', 5, 2)->nullable();
                $table->decimal('report_score', 5, 2)->nullable();
                $table->decimal('overall_score', 5, 2)->nullable();
                $table->text('comment')->nullable();
                $table->json('criteria')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->index(['siwes_placement_id', 'evaluator_type']);
            });
        }

        if (! Schema::hasTable('seminar_sessions')) {
            Schema::create('seminar_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('scheduled_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->timestamp('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(30);
                $table->string('venue')->nullable();
                $table->text('online_url')->nullable();
                $table->foreignId('slide_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->string('status', 30)->default('scheduled');
                $table->text('moderator_notes')->nullable();
                $table->decimal('final_score', 5, 2)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'submission_id']);
                $table->index(['scheduled_at', 'status']);
            });
        }

        if (! Schema::hasTable('seminar_panel_members')) {
            Schema::create('seminar_panel_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('seminar_session_id')->constrained('seminar_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('panelist');
                $table->decimal('score', 5, 2)->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('scored_at')->nullable();
                $table->timestamps();
                $table->unique(['seminar_session_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('seminar_questions')) {
            Schema::create('seminar_questions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('seminar_session_id')->constrained('seminar_sessions')->cascadeOnDelete();
                $table->foreignId('asked_by')->constrained('users')->cascadeOnDelete();
                $table->text('question');
                $table->text('response')->nullable();
                $table->string('status', 30)->default('open');
                $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_questions');
        Schema::dropIfExists('seminar_panel_members');
        Schema::dropIfExists('seminar_sessions');
        Schema::dropIfExists('siwes_evaluations');
        Schema::dropIfExists('siwes_attendance_records');
        Schema::dropIfExists('siwes_log_entries');
        Schema::dropIfExists('siwes_placements');
    }
};
