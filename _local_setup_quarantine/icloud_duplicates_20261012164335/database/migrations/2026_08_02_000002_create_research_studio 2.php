<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_types')) {
            Schema::create('research_types', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
                $table->foreignId('workflow_definition_id')->nullable()->constrained('workflow_definitions')->nullOnDelete();
                $table->string('name');
                $table->string('slug', 100);
                $table->text('description')->nullable();
                $table->json('template_schema')->nullable();
                $table->json('validation_rules')->nullable();
                $table->decimal('similarity_threshold', 5, 2)->default(20);
                $table->boolean('publication_eligible')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['university_id', 'slug']);
            });
        }

        if (! Schema::hasTable('research_projects')) {
            Schema::create('research_projects', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
                $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->foreignId('research_type_id')->constrained('research_types')->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('co_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
                $table->string('title');
                $table->string('research_area')->nullable();
                $table->json('keywords')->nullable();
                $table->text('abstract')->nullable();
                $table->string('status', 50)->default('draft');
                $table->decimal('progress', 5, 2)->default(0);
                $table->date('expected_completion_date')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'department_id', 'status']);
                $table->index(['owner_id', 'status']);
                $table->index(['supervisor_id', 'status']);
            });
        }

        if (! Schema::hasTable('research_project_members')) {
            Schema::create('research_project_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 50)->default('author');
                $table->decimal('contribution_percent', 5, 2)->nullable();
                $table->json('permissions')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('research_sections')) {
            Schema::create('research_sections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('content_document_id')->constrained('content_documents')->cascadeOnDelete();
                $table->string('key', 150);
                $table->string('title');
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_required')->default(true);
                $table->string('status', 50)->default('draft');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'key']);
                $table->index(['research_project_id', 'position']);
            });
        }

        if (! Schema::hasTable('research_corrections')) {
            Schema::create('research_corrections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('research_section_id')->nullable()->constrained('research_sections')->nullOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 100)->default('general');
                $table->text('description');
                $table->string('status', 50)->default('open');
                $table->dateTime('due_at')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['research_project_id', 'status']);
            });
        }

        if (! Schema::hasTable('research_meetings')) {
            Schema::create('research_meetings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('scheduled_by')->constrained('users')->cascadeOnDelete();
                $table->dateTime('scheduled_at');
                $table->string('location')->nullable();
                $table->text('notes')->nullable();
                $table->json('attendance')->nullable();
                $table->json('action_items')->nullable();
                $table->string('status', 50)->default('scheduled');
                $table->timestamps();
                $table->index(['research_project_id', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('research_validation_reports')) {
            Schema::create('research_validation_reports', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ai_analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
                $table->string('status', 50)->default('queued');
                $table->decimal('readiness_score', 5, 2)->nullable();
                $table->decimal('similarity_score', 5, 2)->nullable();
                $table->string('source', 100)->default('rule_engine');
                $table->text('summary')->nullable();
                $table->json('findings')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['research_project_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_validation_reports');
        Schema::dropIfExists('research_meetings');
        Schema::dropIfExists('research_corrections');
        Schema::dropIfExists('research_sections');
        Schema::dropIfExists('research_project_members');
        Schema::dropIfExists('research_projects');
        Schema::dropIfExists('research_types');
    }
};
