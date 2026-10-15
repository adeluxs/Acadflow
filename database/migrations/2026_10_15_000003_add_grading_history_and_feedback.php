<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('submission_grades') && ! Schema::hasColumn('submission_grades', 'criteria_feedback')) {
            Schema::table('submission_grades', function (Blueprint $table) {
                $table->json('criteria_feedback')->nullable()->after('criteria_scores');
            });
        }

        if (! Schema::hasTable('submission_grade_histories')) {
            Schema::create('submission_grade_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('score', 5, 2)->nullable();
                $table->decimal('max_score', 5, 2)->nullable();
                $table->json('criteria_scores')->nullable();
                $table->text('feedback')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_grade_histories');

        if (Schema::hasTable('submission_grades') && Schema::hasColumn('submission_grades', 'criteria_feedback')) {
            Schema::table('submission_grades', function (Blueprint $table) {
                $table->dropColumn('criteria_feedback');
            });
        }
    }
};
