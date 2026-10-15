<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 + Phase 4 student insight and reflection features:
 *  - topic_mastery:          per-topic mastery signal
 *  - self_assessments:       pre-submission rubric self-checks
 *  - readiness_snapshots:    exam readiness estimates
 *  - study_reflections:      metacognitive reflection journal entries
 *  - material_annotations:   highlights and margin notes on materials
 *  - deadline_coach_plans:   AI action plans for upcoming deadlines
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topic_mastery')) {
            Schema::create('topic_mastery', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->string('topic');
                $table->unsignedTinyInteger('mastery')->default(0); // 0..100
                $table->json('signals')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'course_id', 'topic']);
            });
        }

        if (! Schema::hasTable('self_assessments')) {
            Schema::create('self_assessments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('submission_task_id');
                $table->json('checklist')->nullable();
                $table->unsignedTinyInteger('confidence')->default(0); // 0..100
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'submission_task_id']);
            });
        }

        if (! Schema::hasTable('readiness_snapshots')) {
            Schema::create('readiness_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->unsignedTinyInteger('score')->default(0); // 0..100
                $table->json('signals')->nullable();
                $table->text('narrative')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }

        if (! Schema::hasTable('study_reflections')) {
            Schema::create('study_reflections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->string('prompt')->nullable();
                $table->text('reflection');
                $table->text('ai_feedback')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }

        if (! Schema::hasTable('material_annotations')) {
            Schema::create('material_annotations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->unsignedBigInteger('material_id');
                $table->text('quote')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'material_id']);
            });
        }

        if (! Schema::hasTable('deadline_coach_plans')) {
            Schema::create('deadline_coach_plans', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->json('items')->nullable();
                $table->text('summary')->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 150)->nullable();
                $table->string('error_code', 60)->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deadline_coach_plans');
        Schema::dropIfExists('material_annotations');
        Schema::dropIfExists('study_reflections');
        Schema::dropIfExists('readiness_snapshots');
        Schema::dropIfExists('self_assessments');
        Schema::dropIfExists('topic_mastery');
    }
};
