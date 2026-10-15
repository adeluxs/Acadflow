<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 student study tools:
 *  - practice quizzes and attempts (questions stored as JSON)
 *  - spaced-repetition flashcard decks (cards with scheduling stored as JSON)
 *  - personal study plans (items stored as JSON)
 *
 * Structured children are stored as JSON to keep the schema lean while the AI
 * produces variable-length, provider-dependent content. Children remain fully
 * readable and editable by the owning student.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('practice_quizzes')) {
            Schema::create('practice_quizzes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->unsignedBigInteger('material_id')->nullable();
                $table->string('title');
                $table->string('topic')->nullable();
                $table->string('difficulty', 20)->default('mixed');
                $table->string('status', 20)->default('ready'); // ready, failed
                $table->json('questions')->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 150)->nullable();
                $table->string('error_code', 60)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }

        if (! Schema::hasTable('practice_attempts')) {
            Schema::create('practice_attempts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('quiz_id')->constrained('practice_quizzes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->integer('score')->default(0);
                $table->integer('total')->default(0);
                $table->json('answers')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'quiz_id']);
            });
        }

        if (! Schema::hasTable('flashcard_decks')) {
            Schema::create('flashcard_decks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->unsignedBigInteger('material_id')->nullable();
                $table->string('title');
                $table->json('cards')->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 150)->nullable();
                $table->string('error_code', 60)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }

        if (! Schema::hasTable('flashcard_reviews')) {
            Schema::create('flashcard_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('deck_id')->constrained('flashcard_decks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('card_index')->default(0);
                $table->unsignedTinyInteger('rating')->default(3); // 0..5 recall quality
                $table->integer('scheduled_days')->default(0);
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'deck_id']);
            });
        }

        if (! Schema::hasTable('study_plans')) {
            Schema::create('study_plans', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->string('title');
                $table->string('status', 20)->default('pending'); // pending, generating, ready, failed
                $table->json('items')->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 150)->nullable();
                $table->string('error_code', 60)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'course_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcard_decks');
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('practice_quizzes');
    }
};
