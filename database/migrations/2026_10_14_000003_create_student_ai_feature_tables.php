<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 student AI features:
 *  - student_learning_profiles: personalization inputs for AI prompts
 *  - study_conversations:      saved, course-scoped companion threads
 *  - study_notes:              student-owned notes, optionally saved from AI answers
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_learning_profiles')) {
            Schema::create('student_learning_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('pace', 20)->nullable();            // slow, steady, fast
                $table->string('level', 20)->nullable();           // beginner, intermediate, advanced
                $table->string('explanation_style', 30)->nullable(); // simple, balanced, detailed
                $table->string('language', 10)->nullable();        // e.g. en, fr, ha
                $table->string('reading_level', 20)->nullable();   // simple, standard, advanced
                $table->text('goals')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('study_conversations')) {
            Schema::create('study_conversations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('course_id')->nullable()->index();
                $table->string('title');
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }

        if (! Schema::hasTable('study_notes')) {
            Schema::create('study_notes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('course_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->string('title');
                $table->longText('content')->nullable();
                $table->string('source', 20)->default('manual'); // manual, ai_answer, material_summary
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_notes');
        Schema::dropIfExists('study_conversations');
        Schema::dropIfExists('student_learning_profiles');
    }
};
