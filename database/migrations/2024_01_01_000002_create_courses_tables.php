<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic Session & Semester
        if (!Schema::hasTable('academic_sessions')) {
            Schema::create('academic_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->string('name', 20);
                $table->unique(['university_id', 'name']);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_current')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('semesters')) {
            Schema::create('semesters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->string('name', 20);
                $table->integer('number');
                $table->date('start_date');
                $table->date('end_date');
                $table->date('grading_deadline')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Courses
        if (!Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('credit_hours')->default(3);
                $table->string('level', 10);
                $table->string('semester', 10);
                $table->enum('type', ['compulsory', 'elective'])->default('compulsory');
                $table->integer('max_capacity')->nullable();
                $table->json('submission_types')->default(json_encode(['assignment', 'project', 'siwes']));
                $table->integer('pass_mark')->default(40);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Enrollments
        if (!Schema::hasTable('enrollments')) {
            Schema::create('enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->enum('status', ['enrolled', 'dropped', 'completed'])->default('enrolled');
                $table->timestamp('enrolled_at');
                $table->timestamps();
                $table->unique(['user_id', 'course_id', 'semester_id']);
            });
        }

        // Lecturer Course Assignments
        if (!Schema::hasTable('lecturer_course_assignments')) {
            Schema::create('lecturer_course_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->boolean('is_coordinator')->default(false);
                $table->timestamps();
                $table->unique(['course_id', 'user_id', 'semester_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_course_assignments');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_sessions');
    }
};
