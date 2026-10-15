<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('submission_tasks') && ! Schema::hasColumn('submission_tasks', 'type_metadata')) {
            Schema::table('submission_tasks', function (Blueprint $table) {
                // Type-specific fields (project, siwes, seminar, group) stored as
                // a validated JSON document so each assignment type can carry its
                // own creation schema without a table per type.
                $table->json('type_metadata')->nullable()->after('submission_requirements_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('submission_tasks') && Schema::hasColumn('submission_tasks', 'type_metadata')) {
            Schema::table('submission_tasks', function (Blueprint $table) {
                $table->dropColumn('type_metadata');
            });
        }
    }
};
