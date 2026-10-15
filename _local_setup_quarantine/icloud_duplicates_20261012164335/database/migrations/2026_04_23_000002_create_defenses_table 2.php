<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('defenses')) {
            Schema::create('defenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('scheduled_at');
                $table->integer('duration_minutes')->default(30);
                $table->string('venue')->nullable();
                $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
                $table->text('notes')->nullable();
                $table->decimal('score', 5, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('defenses');
    }
};
