<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_materials')) {
            Schema::create('course_materials', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

                // Material details
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('type', ['lecture_note', 'slides', 'reading', 'video', 'assignment', 'exam', 'reference', 'other'])->default('lecture_note');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->bigInteger('file_size')->nullable();

                // Organization
                $table->string('topic')->nullable(); // e.g., "Introduction", "Data Structures"
                $table->integer('week_number')->nullable(); // Week 1, 2, etc.
                $table->integer('sequence_order')->default(0); // Order within topic/week

                // Visibility & Access
                $table->boolean('is_public')->default(false); // Public vs enrolled only
                $table->boolean('requires_enrollment')->default(true);
                $table->boolean('is_visible')->default(true);

                // Tracking
                $table->timestamp('published_at')->nullable();
                $table->integer('download_count')->default(0);

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['course_id', 'semester_id']);
                $table->index(['topic', 'week_number']);
                $table->index('is_visible');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
