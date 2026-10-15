<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores AI-generated lecturer content drafts produced by queued automation.
 *
 * Drafts are advisory: a background job generates them, and the lecturer
 * reviews and explicitly applies one before it becomes a course material.
 * Nothing here is course content until the lecturer applies it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_content_drafts')) {
            return;
        }

        Schema::create('ai_content_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('university_id')->nullable()->index();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('feature', 60)->default('lecturer_assistant');
            $table->string('action', 40);
            $table->text('instruction');
            $table->longText('content')->nullable();
            $table->string('provider', 60)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('error_code', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_drafts');
    }
};
