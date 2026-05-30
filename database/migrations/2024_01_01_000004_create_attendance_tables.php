<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance Sessions
        if (!Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
                $table->string('qr_code');
                $table->timestamp('qr_expires_at')->useCurrent();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
                $table->enum('status', ['active', 'closed', 'cancelled'])->default('active');
                $table->integer('geofence_radius')->default(100);
                $table->integer('check_in_window')->default(30);
                $table->integer('late_threshold')->default(15);
                $table->timestamps();
            });
        }

        // Attendance Records
        if (!Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['present', 'late', 'absent', 'invalid', 'pending'])->default('pending');
                $table->timestamp('check_in_at')->useCurrent();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('device_fingerprint', 255)->nullable();
                $table->boolean('is_verified')->default(false);
                $table->text('verification_notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['session_id', 'user_id']);
            });
        }

        // Attendance Rules
        if (!Schema::hasTable('attendance_rules')) {
            Schema::create('attendance_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->integer('min_attendance')->default(75);
                $table->boolean('allow_remote')->default(false);
                $table->boolean('require_gps')->default(true);
                $table->boolean('require_wifi')->default(false);
                $table->integer('qr_refresh_seconds')->default(60);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
