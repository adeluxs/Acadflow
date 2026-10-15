<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds in-system authoring support to course materials.
 *
 * Lecturers can now compose materials directly with the rich editor instead of
 * uploading a file. Authored content is stored as sanitized HTML in `content`,
 * while `file_name` / `file_path` become nullable so an authored material does
 * not require an uploaded file. Existing uploaded materials keep their `source`
 * of `upload` and continue to work unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_materials')) {
            return;
        }

        Schema::table('course_materials', function (Blueprint $table): void {
            if (! Schema::hasColumn('course_materials', 'content')) {
                $table->longText('content')->nullable();
            }
            if (! Schema::hasColumn('course_materials', 'content_format')) {
                $table->string('content_format', 20)->default('html');
            }
            if (! Schema::hasColumn('course_materials', 'source')) {
                $table->string('source', 20)->default('upload');
            }
            if (! Schema::hasColumn('course_materials', 'content_updated_at')) {
                $table->timestamp('content_updated_at')->nullable();
            }
        });

        // Authored materials have no uploaded file. Relax the original NOT NULL
        // file constraints without touching existing rows.
        Schema::table('course_materials', function (Blueprint $table): void {
            if (Schema::hasColumn('course_materials', 'file_name')) {
                $table->string('file_name')->nullable()->change();
            }
            if (Schema::hasColumn('course_materials', 'file_path')) {
                $table->string('file_path')->nullable()->change();
            }
            if (Schema::hasColumn('course_materials', 'disk')) {
                $table->string('disk')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_materials')) {
            return;
        }

        Schema::table('course_materials', function (Blueprint $table): void {
            foreach (['content', 'content_format', 'source', 'content_updated_at'] as $column) {
                if (Schema::hasColumn('course_materials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
