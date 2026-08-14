<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('submission_versions')) {
            return;
        }

        if (! Schema::hasColumn('submission_versions', 'disk')) {
            Schema::table('submission_versions', function (Blueprint $table): void {
                $table->string('disk', 60)->default('local')->after('file_path');
            });
        }

        if (! Schema::hasColumn('submission_versions', 'media_asset_id')) {
            Schema::table('submission_versions', function (Blueprint $table): void {
                $table->foreignId('media_asset_id')->nullable()->after('disk')->constrained('media_assets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('submission_versions')) {
            return;
        }

        if (Schema::hasColumn('submission_versions', 'media_asset_id')) {
            Schema::table('submission_versions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('media_asset_id');
            });
        }

        if (Schema::hasColumn('submission_versions', 'disk')) {
            Schema::table('submission_versions', function (Blueprint $table): void {
                $table->dropColumn('disk');
            });
        }
    }
};
