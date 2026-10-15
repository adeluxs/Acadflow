<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_template_versions')) {
            Schema::create('research_template_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_type_id')->constrained('research_types')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('name');
                $table->json('template_schema');
                $table->json('validation_rules')->nullable();
                $table->string('citation_style', 30)->default('apa');
                $table->boolean('is_active')->default(true);
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('retired_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['research_type_id', 'version']);
                $table->index(['research_type_id', 'is_active']);
            });
        }

        if (Schema::hasTable('research_projects') && ! Schema::hasColumn('research_projects', 'research_template_version_id')) {
            Schema::table('research_projects', function (Blueprint $table): void {
                $table->foreignId('research_template_version_id')->nullable()->after('research_type_id')->constrained('research_template_versions')->nullOnDelete();
                $table->string('specialization_type', 40)->nullable()->after('status');
                $table->timestamp('last_activity_at')->nullable()->after('archived_at');
                $table->index(['research_template_version_id', 'status'], 'research_projects_template_status');
            });
        }

        if (! Schema::hasTable('research_milestones')) {
            Schema::create('research_milestones', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('workflow_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('weight', 5, 2)->default(0);
                $table->string('status', 30)->default('pending');
                $table->dateTime('due_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['research_project_id', 'status', 'due_at']);
            });
        }

        if (! Schema::hasTable('research_tasks')) {
            Schema::create('research_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('research_section_id')->nullable()->constrained('research_sections')->nullOnDelete();
                $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->string('status', 30)->default('open');
                $table->dateTime('due_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['research_project_id', 'assigned_to', 'status']);
                $table->index(['due_at', 'status']);
            });
        }

        if (! Schema::hasTable('research_section_authorships')) {
            Schema::create('research_section_authorships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_section_id')->constrained('research_sections')->cascadeOnDelete();
                $table->foreignId('content_version_id')->nullable()->constrained('content_versions')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->integer('words_added')->default(0);
                $table->integer('words_removed')->default(0);
                $table->integer('characters_added')->default(0);
                $table->integer('characters_removed')->default(0);
                $table->decimal('contribution_score', 10, 4)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['research_section_id', 'user_id']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('research_meetings')) {
            Schema::table('research_meetings', function (Blueprint $table): void {
                if (! Schema::hasColumn('research_meetings', 'agenda')) {
                    $table->text('agenda')->nullable()->after('location');
                }
                if (! Schema::hasColumn('research_meetings', 'online_url')) {
                    $table->text('online_url')->nullable()->after('location');
                }
                if (! Schema::hasColumn('research_meetings', 'duration_minutes')) {
                    $table->unsignedSmallInteger('duration_minutes')->default(60)->after('scheduled_at');
                }
                if (! Schema::hasColumn('research_meetings', 'calendar_uid')) {
                    $table->string('calendar_uid')->nullable()->unique()->after('status');
                }
                if (! Schema::hasColumn('research_meetings', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                }
            });
        }

        if (! Schema::hasTable('research_meeting_attendees')) {
            Schema::create('research_meeting_attendees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_meeting_id')->constrained('research_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('response', 30)->default('pending');
                $table->boolean('attended')->default(false);
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['research_meeting_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('research_action_items')) {
            Schema::create('research_action_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_meeting_id')->constrained('research_meetings')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->string('status', 30)->default('open');
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->index(['assigned_to', 'status', 'due_at']);
            });
        }

        if (! Schema::hasTable('research_meeting_reminders')) {
            Schema::create('research_meeting_reminders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_meeting_id')->constrained('research_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->dateTime('remind_at');
                $table->string('channel', 30)->default('in_app');
                $table->string('status', 30)->default('pending');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['research_meeting_id', 'user_id', 'remind_at', 'channel'], 'research_meeting_reminders_unique');
                $table->index(['status', 'remind_at']);
            });
        }

        if (! Schema::hasTable('research_archives')) {
            Schema::create('research_archives', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('processing');
                $table->string('disk', 50)->default('local');
                $table->string('package_path')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->json('manifest')->nullable();
                $table->timestamp('sealed_at')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'version']);
                $table->index(['research_project_id', 'status']);
            });
        }

        if (! Schema::hasTable('research_amendments')) {
            Schema::create('research_amendments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('research_archive_id')->nullable()->constrained('research_archives')->nullOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
                $table->text('reason');
                $table->json('requested_changes')->nullable();
                $table->string('status', 30)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->index(['research_project_id', 'status']);
            });
        }

        if (! Schema::hasTable('research_datasets')) {
            Schema::create('research_datasets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('access_level', 30)->default('project');
                $table->json('schema_metadata')->nullable();
                $table->json('ethics_metadata')->nullable();
                $table->timestamps();
                $table->index(['research_project_id', 'access_level']);
            });
        }

        if (! Schema::hasTable('research_specialized_links')) {
            Schema::create('research_specialized_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->string('workspace_type', 40);
                $table->string('source_type', 120);
                $table->unsignedBigInteger('source_id');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->unique(['research_project_id', 'workspace_type', 'source_type', 'source_id'], 'research_specialized_links_unique');
            });
        }

        if (! Schema::hasTable('research_literature_notes')) {
            Schema::create('research_literature_notes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('research_project_id')->constrained('research_projects')->cascadeOnDelete();
                $table->foreignId('academic_reference_id')->constrained('academic_references')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->text('summary')->nullable();
                $table->text('methodology')->nullable();
                $table->text('findings')->nullable();
                $table->text('limitations')->nullable();
                $table->text('contradictions')->nullable();
                $table->text('research_gap')->nullable();
                $table->json('keywords')->nullable();
                $table->foreignId('ai_analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
                $table->timestamps();
                $table->unique(['research_project_id', 'academic_reference_id', 'created_by'], 'research_literature_notes_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_literature_notes');
        Schema::dropIfExists('research_specialized_links');
        Schema::dropIfExists('research_datasets');
        Schema::dropIfExists('research_amendments');
        Schema::dropIfExists('research_archives');
        Schema::dropIfExists('research_meeting_reminders');
        Schema::dropIfExists('research_action_items');
        Schema::dropIfExists('research_meeting_attendees');
        Schema::dropIfExists('research_section_authorships');
        Schema::dropIfExists('research_tasks');
        Schema::dropIfExists('research_milestones');

        if (Schema::hasTable('research_projects') && Schema::hasColumn('research_projects', 'research_template_version_id')) {
            Schema::table('research_projects', function (Blueprint $table): void {
                $table->dropForeign(['research_template_version_id']);
                $table->dropIndex('research_projects_template_status');
                $table->dropColumn(['research_template_version_id', 'specialization_type', 'last_activity_at']);
            });
        }

        Schema::dropIfExists('research_template_versions');
    }
};
