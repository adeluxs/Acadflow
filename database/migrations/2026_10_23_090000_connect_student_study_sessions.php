<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_study_sessions')) {
            Schema::create('student_study_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('material_id')->nullable()->constrained('course_materials')->cascadeOnDelete();
                $table->foreignId('note_id')->nullable()->constrained('study_notes')->cascadeOnDelete();
                $table->text('selected_text')->nullable();
                $table->text('question')->nullable();
                $table->text('explanation')->nullable();
                $table->string('source_fingerprint', 64)->nullable();
                $table->dateTime('explained_at')->nullable();
                $table->dateTime('last_studied_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'material_id'], 'study_session_user_material_unique');
                $table->unique(['user_id', 'note_id'], 'study_session_user_note_unique');
                $table->index(['user_id', 'last_studied_at', 'id'], 'study_session_resume_idx');
            });
        }
        if (! Schema::hasColumn('practice_quizzes', 'study_session_id')) {
            Schema::table('practice_quizzes', fn (Blueprint $table) => $table->foreignId('study_session_id')->nullable()->constrained('student_study_sessions')->cascadeOnDelete());
        }
        foreach (['next_review_at', 'last_practiced_at'] as $column) {
            if (! Schema::hasColumn('practice_quizzes', $column)) {
                Schema::table('practice_quizzes', fn (Blueprint $table) => $table->dateTime($column)->nullable());
            }
        }
        if (! Schema::hasColumn('practice_quizzes', 'review_interval_days')) {
            Schema::table('practice_quizzes', fn (Blueprint $table) => $table->unsignedSmallInteger('review_interval_days')->default(0));
        }
        if (! Schema::hasIndex('practice_quizzes', 'practice_user_review_idx')) {
            Schema::table('practice_quizzes', fn (Blueprint $table) => $table->index(['user_id', 'next_review_at', 'id'], 'practice_user_review_idx'));
        }
        if (! Schema::hasColumn('practice_attempts', 'submission_token')) {
            Schema::table('practice_attempts', fn (Blueprint $table) => $table->uuid('submission_token')->nullable());
        }
        if (! Schema::hasIndex('practice_attempts', 'practice_attempt_submission_unique')) {
            Schema::table('practice_attempts', fn (Blueprint $table) => $table->unique(['quiz_id', 'user_id', 'submission_token'], 'practice_attempt_submission_unique'));
        }
        if (! Schema::hasIndex('practice_attempts', 'practice_user_completed_idx')) {
            Schema::table('practice_attempts', fn (Blueprint $table) => $table->index(['user_id', 'completed_at', 'quiz_id'], 'practice_user_completed_idx'));
        }
    }

    public function down(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table): void {
            $table->dropIndex('practice_user_completed_idx');
            $table->dropUnique('practice_attempt_submission_unique');
            $table->dropColumn('submission_token');
        });
        Schema::table('practice_quizzes', function (Blueprint $table): void {
            $table->dropIndex('practice_user_review_idx');
            $table->dropConstrainedForeignId('study_session_id');
            $table->dropColumn(['next_review_at', 'last_practiced_at', 'review_interval_days']);
        });
        Schema::dropIfExists('student_study_sessions');
    }
};
