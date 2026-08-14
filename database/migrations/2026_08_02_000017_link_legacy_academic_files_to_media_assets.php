<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMediaLink('course_materials');
        $this->addMediaLink('submission_task_attachments');
    }

    public function down(): void
    {
        $this->dropMediaLink('submission_task_attachments');
        $this->dropMediaLink('course_materials');
    }

    private function addMediaLink(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'disk')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('disk', 60)->default('local')->after('file_path');
            });
        }

        if (! Schema::hasColumn($tableName, 'media_asset_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('media_asset_id')->nullable()->after('disk')->constrained('media_assets')->nullOnDelete();
            });
        }
    }

    private function dropMediaLink(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, 'media_asset_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('media_asset_id');
            });
        }

        if (Schema::hasColumn($tableName, 'disk')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('disk');
            });
        }
    }
};
