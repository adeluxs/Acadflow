<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table): void {
            if (! Schema::hasColumn('workflow_stages', 'deadline_days')) {
                $table->unsignedInteger('deadline_days')->nullable()->after('position');
            }
            if (! Schema::hasColumn('workflow_stages', 'requirements')) {
                $table->json('requirements')->nullable()->after('settings');
            }
        });

        Schema::table('content_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('content_documents', 'editor_mode')) {
                $table->string('editor_mode', 30)->default('rich_text')->after('document_type');
            }
            if (! Schema::hasColumn('content_documents', 'word_count')) {
                $table->unsignedInteger('word_count')->default(0)->after('version_number');
            }
            if (! Schema::hasColumn('content_documents', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('autosaved_at');
            }
            if (! Schema::hasColumn('content_documents', 'recovery_metadata')) {
                $table->json('recovery_metadata')->nullable()->after('metadata');
            }
        });

        Schema::table('research_sections', function (Blueprint $table): void {
            if (! Schema::hasColumn('research_sections', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('content_document_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('research_sections', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('research_sections', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('research_sections', 'completion_percent')) {
                $table->decimal('completion_percent', 5, 2)->default(0)->after('status');
            }
        });

        Schema::table('knowledge_publications', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_publications', 'language')) {
                $table->string('language', 12)->default('en')->after('content_type');
            }
            if (! Schema::hasColumn('knowledge_publications', 'doi')) {
                $table->string('doi')->nullable()->after('slug')->index();
            }
            if (! Schema::hasColumn('knowledge_publications', 'reading_time_minutes')) {
                $table->unsignedInteger('reading_time_minutes')->default(0)->after('comment_count');
            }
            if (! Schema::hasColumn('knowledge_publications', 'download_count')) {
                $table->unsignedBigInteger('download_count')->default(0)->after('comment_count');
            }
            if (! Schema::hasColumn('knowledge_publications', 'share_count')) {
                $table->unsignedBigInteger('share_count')->default(0)->after('comment_count');
            }
            if (! Schema::hasColumn('knowledge_publications', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('knowledge_publications', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('featured_at');
            }
        });

        Schema::table('academic_references', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_references', 'abstract')) {
                $table->longText('abstract')->nullable()->after('title');
            }
            if (! Schema::hasColumn('academic_references', 'isbn')) {
                $table->string('isbn', 30)->nullable()->after('doi');
            }
            if (! Schema::hasColumn('academic_references', 'external_ids')) {
                $table->json('external_ids')->nullable()->after('citation_key');
            }
            if (! Schema::hasColumn('academic_references', 'pdf_media_asset_id')) {
                $table->foreignId('pdf_media_asset_id')->nullable()->after('url')->constrained('media_assets')->nullOnDelete();
            }
        });

        Schema::table('feature_flags', function (Blueprint $table): void {
            if (! Schema::hasColumn('feature_flags', 'settings')) {
                $table->json('settings')->nullable()->after('description');
            }
        });

        if (! Schema::hasTable('feature_flag_overrides')) {
            Schema::create('feature_flag_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('feature_flag_id')->constrained('feature_flags')->cascadeOnDelete();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->boolean('is_enabled');
                $table->json('settings')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['feature_flag_id', 'university_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');

        Schema::table('feature_flags', function (Blueprint $table): void {
            if (Schema::hasColumn('feature_flags', 'settings')) {
                $table->dropColumn('settings');
            }
        });

        Schema::table('academic_references', function (Blueprint $table): void {
            if (Schema::hasColumn('academic_references', 'pdf_media_asset_id')) {
                $table->dropForeign(['pdf_media_asset_id']);
            }
            $columns = array_values(array_filter(['abstract', 'isbn', 'external_ids', 'pdf_media_asset_id'], fn (string $column): bool => Schema::hasColumn('academic_references', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('knowledge_publications', function (Blueprint $table): void {
            $columns = array_values(array_filter(['language', 'doi', 'reading_time_minutes', 'download_count', 'share_count', 'featured_at', 'pinned_at'], fn (string $column): bool => Schema::hasColumn('knowledge_publications', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('research_sections', function (Blueprint $table): void {
            foreach (['created_by', 'locked_by'] as $column) {
                if (Schema::hasColumn('research_sections', $column)) {
                    $table->dropForeign([$column]);
                }
            }
            $columns = array_values(array_filter(['created_by', 'locked_by', 'locked_at', 'completion_percent'], fn (string $column): bool => Schema::hasColumn('research_sections', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('content_documents', function (Blueprint $table): void {
            $columns = array_values(array_filter(['editor_mode', 'word_count', 'last_synced_at', 'recovery_metadata'], fn (string $column): bool => Schema::hasColumn('content_documents', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $columns = array_values(array_filter(['deadline_days', 'requirements'], fn (string $column): bool => Schema::hasColumn('workflow_stages', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
