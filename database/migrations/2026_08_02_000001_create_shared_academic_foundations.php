<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions')) {
            Schema::create('workflow_definitions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('name');
                $table->string('subject_type', 100);
                $table->text('description')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['university_id', 'key']);
                $table->index(['subject_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('workflow_stages')) {
            Schema::create('workflow_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('name');
                $table->unsignedInteger('position')->default(0);
                $table->json('actor_roles')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_initial')->default(false);
                $table->boolean('is_final')->default(false);
                $table->timestamps();
                $table->unique(['workflow_definition_id', 'key']);
                $table->index(['workflow_definition_id', 'position']);
            });
        }

        if (! Schema::hasTable('workflow_instances')) {
            Schema::create('workflow_instances', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->restrictOnDelete();
                $table->string('subject_type', 100);
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('active');
                $table->json('context')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
                $table->index(['workflow_definition_id', 'status']);
            });
        }

        if (! Schema::hasTable('workflow_transition_logs')) {
            Schema::create('workflow_transition_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
                $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
                $table->foreignId('to_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100);
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workflow_instance_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('content_documents')) {
            Schema::create('content_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('document_type', 100);
                $table->string('title');
                $table->longText('body')->nullable();
                $table->string('status', 50)->default('draft');
                $table->string('visibility', 50)->default('private');
                $table->unsignedInteger('version_number')->default(1);
                $table->json('metadata')->nullable();
                $table->timestamp('autosaved_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['university_id', 'document_type', 'status']);
                $table->index(['owner_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('content_versions')) {
            Schema::create('content_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('content_document_id')->constrained('content_documents')->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('version_number');
                $table->longText('body')->nullable();
                $table->string('change_summary')->nullable();
                $table->boolean('is_snapshot')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['content_document_id', 'version_number']);
                $table->index(['content_document_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('content_comments')) {
            Schema::create('content_comments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('content_document_id')->constrained('content_documents')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('content_comments')->nullOnDelete();
                $table->string('section_key', 150)->nullable();
                $table->string('type', 50)->default('comment');
                $table->text('body');
                $table->string('status', 50)->default('open');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['content_document_id', 'section_key', 'status']);
            });
        }

        if (! Schema::hasTable('academic_references')) {
            Schema::create('academic_references', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('university_id')->nullable()->constrained('universities')->cascadeOnDelete();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->json('authors')->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->string('source_type', 50)->default('other');
                $table->string('journal')->nullable();
                $table->string('publisher')->nullable();
                $table->string('doi')->nullable();
                $table->text('url')->nullable();
                $table->string('citation_key', 100)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['university_id', 'owner_id']);
                $table->index('doi');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_references');
        Schema::dropIfExists('content_comments');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('content_documents');
        Schema::dropIfExists('workflow_transition_logs');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_definitions');
    }
};
