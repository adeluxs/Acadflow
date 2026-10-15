<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('submission_grades') && ! Schema::hasColumn('submission_grades', 'criteria_scores')) {
            Schema::table('submission_grades', function (Blueprint $table) {
                $table->json('criteria_scores')->nullable()->after('feedback');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('submission_grades') && Schema::hasColumn('submission_grades', 'criteria_scores')) {
            Schema::table('submission_grades', function (Blueprint $table) {
                $table->dropColumn('criteria_scores');
            });
        }
    }
};
